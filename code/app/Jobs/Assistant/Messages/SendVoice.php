<?php

namespace App\Jobs\Assistant\Messages;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Assistant\MessagesModel;
use App\Models\Socials\SocialsModel;

class SendVoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $params;
    public $message;
    public $initializedSocials = [];

    protected $voiceServers = [
        'https://voice1.example.com',
        'https://voice2.example.com'
    ];

    public function __construct($params = null)
    {
        $this->params = $params;
        $projectName = env('PROJECTNAME', 'core');
        $localServer = "http://voice-{$projectName}:5000";
        array_unshift($this->voiceServers, $localServer);

        Log::info('SendVoice job initialized', [
            'params' => $this->params,
            'voiceServers' => $this->voiceServers
        ]);
    }

    public function handle()
    {
        $this->message = MessagesModel::find($this->params['message_id']);
        if (!$this->message) {
            Log::error('Message not found', ['message_id' => $this->params['message_id']]);
            return;
        }

        $social = SocialsModel::find($this->message->soc);

        if (!isset($this->initializedSocials[$this->message->soc])) {
            $p35 = $social->propertyById(35)->pivot->value ?? null;
            if (is_null($p35)) {
                $this->logAndNotify('social_init', 'Класс социальной сети не найден');
                return;
            }
            $this->initializedSocials[$this->message->soc] = new $p35;
        }

        $filePath = storage_path('app/voice/' . $this->message->info['file_unique_id']);
        if (!file_exists($filePath)) {
            $this->logAndNotify('no_file', 'Файл голосового сообщения не найден');
            return;
        }

        $server = $this->getAvailableServer();
        if (!$server) {
            $this->logAndNotify('no_server', 'Нет доступного голосового сервера');
            return;
        }

        $projectName = env('PROJECTNAME', 'core');
        $localServer = "http://voice-{$projectName}";
        $isLocalServer = (parse_url($server, PHP_URL_HOST) === parse_url($localServer, PHP_URL_HOST));

        // Выбираем callback URL в зависимости от типа сервера
        $callbackUrl = env('APP_URL');

        try {
            $fileSize = filesize($filePath);
            $fileContent = file_get_contents($filePath);
            $fileName = $this->message->info['file_unique_id'];
            $targetUrl = rtrim($server, '/') . '/transcribe';

            Log::info('Детали запроса к голосовому серверу', [
                'message_id' => $this->message->id,
                'server' => $server,
                'target_url' => $targetUrl,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'file_name' => $fileName,
                'callbackUrl' => $callbackUrl,
                'file_exists' => file_exists($filePath),
                'file_readable' => is_readable($filePath),
                'content_length' => strlen($fileContent)
            ]);

            $response = Http::attach(
                'file',
                $fileContent,
                $fileName
            )->timeout(60)->post($targetUrl, [
                'message_id' => $this->params['message_id'],
                'callbackUrl' => $callbackUrl,
                'filename' => $fileName,
            ]);

            $responseBody = $response->body();
            $result = $response->json();
            $statusCode = $response->status();

            Log::info('Ответ от голосового сервера получен', [
                'message_id' => $this->message->id,
                'status_code' => $statusCode,
                'headers' => $response->headers(),
                'raw_response' => $responseBody,
                'parsed_response' => $result,
                'response_size' => strlen($responseBody)
            ]);

            // Обрабатываем разные статусы ответа
            if ($statusCode === 202) {
                // Сервер принял файл в обработку - сохраняем статус
                $this->handleAcceptedResponse($result);
                return;
            } elseif ($statusCode === 200) {
                // Немедленный ответ с результатом
                $this->handleImmediateResponse($result, $responseBody, $statusCode);
                return;
            } else {
                // Ошибка
                $this->logAndNotify('server_error', "Сервер вернул ошибку: {$statusCode}");
                return;
            }

        } catch (\Exception $e) {
            Log::error('Ошибка при запросе к голосовому серверу', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->logAndNotify('recognition_error', 'Ошибка распознавания аудио: ' . $e->getMessage());
            return;
        }
    }

    protected function handleImmediateResponse(array $result, string $responseBody, int $statusCode): void
    {
        // Сохраняем результат
        $this->saveVoiceResponse($result, $responseBody, $statusCode);
        $answer = $this->extractTranscriptionText($result);

        Log::info('Немедленное распознавание завершено', [
            'message_id' => $this->message->id,
            'answer' => $answer
        ]);

        // ⚡️ ВАЖНО: Обновляем ОСНОВНОЙ текст сообщения для следующих фильтров
        $this->message->text = $answer;
        
        // Обновляем статус сообщения - обработка завершена
        $info = $this->message->info;
        if (isset($info['voice_processing'])) {
            $info['voice_processing']['status'] = 'completed';
            $info['voice_processing']['completed_at'] = now()->toISOString();
            $this->message->info = $info;
        }
        $this->message->save();

        // Отправляем результат пользователю
        try {
            $this->initializedSocials[$this->message->soc]->sendMessage(
                $this->message->chat_id,
                $answer,
                $this->message->info
            );
        } catch (\Exception $e) {
            Log::error('Ошибка отправки результата пользователю', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage()
            ]);
        }

        // ⚡️ ВАЖНО: Продолжаем цепочку фильтров
        $this->continueFilterChain();
    }

    protected function extractTranscriptionText(array $result): string
    {
        if (isset($result['transcriptions']) &&
            isset($result['detected_language']) &&
            isset($result['transcriptions'][$result['detected_language']])) {
            return $result['transcriptions'][$result['detected_language']];
        }

        if (isset($result['text'])) {
            return $result['text'];
        }

        if (isset($result['result'])) {
            return is_array($result['result']) ? implode(' ', $result['result']) : $result['result'];
        }

        return "Не удалось распознать голосовое сообщение";
    }

    protected function saveVoiceResponse(array $parsedResponse, string $rawResponse, int $statusCode): void
    {
        try {
            $info = $this->message->info;
            $info['voice_response'] = [
                'timestamp' => now()->toISOString(),
                'status_code' => $statusCode,
                'parsed' => $parsedResponse,
                'raw' => $rawResponse,
                'server' => $this->getAvailableServer()
            ];
            $info['voice_recognition'] = [
                'text' => $this->extractTranscriptionText($parsedResponse),
                'detected_language' => $parsedResponse['detected_language'] ?? null,
                'confidence' => $parsedResponse['confidence'] ?? null
            ];

            $this->message->info = $info;
            $this->message->save();

        } catch (\Exception $e) {
            Log::error('Ошибка сохранения ответа голосового сервера', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function getAvailableServer()
    {
        foreach ($this->voiceServers as $server) {
            try {
                $healthUrl = rtrim($server, '/') . '/health';
                $response = Http::timeout(2)->get($healthUrl);

                if ($response->ok()) {
                    return $server;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        return null;
    }

    protected function logAndNotify(string $errorCode, string $logMessage)
    {
        Log::error($logMessage, [
            'message_id' => $this->message->id ?? null,
            'error_code' => $errorCode
        ]);

        if ($this->message) {
            $info = $this->message->info;
            $info['voice_text_error'] = $logMessage;
            $info['error_timestamp'] = now()->toISOString();
            $info['error_code'] = $errorCode;
            $this->message->info = $info;
            $this->message->save();
        }

        $userMessage = match ($errorCode) {
            'no_file' => "Файл вашего голосового сообщения не найден. Попробуйте отправить снова.",
            'no_server' => "Сервер распознавания голосовых сообщений недоступен. Попробуйте позже.",
            'recognition_error' => "Произошла ошибка при распознавании вашего голосового сообщения. Попробуйте снова.",
            'social_init' => "Ошибка обработки социальной сети. Обратитесь в поддержку.",
            'invalid_response' => "Сервер распознавания вернул некорректный ответ. Попробуйте позже.",
            default => "Произошла неизвестная ошибка при обработке голосового сообщения."
        };

        if (isset($this->initializedSocials[$this->message->soc])) {
            try {
                $this->initializedSocials[$this->message->soc]->sendMessage(
                    $this->message->chat_id,
                    $userMessage,
                    $this->message->info
                );
            } catch (\Exception $e) {
                Log::error('Ошибка отправки уведомления об ошибке', [
                    'message_id' => $this->message->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Продолжение цепочки фильтров после обработки голоса
     */
    protected function continueFilterChain(): void
    {
        try {
            Log::info('Продолжаем цепочку фильтров после обработки голоса', [
                'message_id' => $this->message->id
            ]);

            // Получаем информацию о голосовом фильтре, который запустил обработку
            $voiceFilterId = $this->message->info['voice_processing']['filter_id'] ?? null;
            
            if (!$voiceFilterId) {
                Log::warning('ID голосового фильтра не найден, продолжаем с начала цепочки', [
                    'message_id' => $this->message->id
                ]);
                $this->startFilterChain();
                return;
            }

            // Продолжаем цепочку фильтров СЛЕДУЮЩИМ после голосового фильтра
            $filterProcessor = new \App\Helpers\Assistant\FilterProcessor();
            $filterProcessor::dispatchNextFilter($this->message, $voiceFilterId);

            Log::info('Цепочка фильтров продолжена', [
                'message_id' => $this->message->id,
                'last_filter_id' => $voiceFilterId
            ]);

        } catch (\Throwable $e) {
            Log::error('Ошибка при продолжении цепочки фильтров', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Запуск цепочки фильтров с начала
     */
    protected function startFilterChain(): void
    {
        try {
            $filterProcessor = new \App\Helpers\Assistant\FilterProcessor();
            $filterProcessor::startFilterChain($this->message);
            
            Log::info('Цепочка фильтров запущена с начала', [
                'message_id' => $this->message->id
            ]);
        } catch (\Throwable $e) {
            Log::error('Ошибка запуска цепочки фильтров', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function handleAcceptedResponse(array $result): void
    {
        // Сохраняем информацию о том, что ждем callback
        $info = $this->message->info;
        $info['voice_processing'] = [
            'status' => 'processing',
            'started_at' => now()->toISOString(),
            'server_response' => $result,
            'expected_callback' => true,
            'filter_id' => $info['voice_processing']['filter_id'] ?? null // Сохраняем ID фильтра
        ];
        
        $this->message->info = $info;
        $this->message->save();

        Log::info('Голосовое сообщение принято в обработку', [
            'message_id' => $this->message->id,
            'response' => $result
        ]);

        // Отправляем уведомление пользователю
        try {
            $this->initializedSocials[$this->message->soc]->sendMessage(
                $this->message->chat_id,
                "✅ Ваше голосовое сообщение принято в обработку. Результат будет скоро...",
                $this->message->info
            );
        } catch (\Exception $e) {
            Log::error('Ошибка отправки уведомления о начале обработки', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage()
            ]);
        }
        
        // ⚡️ ВАЖНО: Для асинхронного случая цепочка продолжится через callback
        // в Voice::processVoiceCallback, который уже вызывает continueFilterProcessing
    }
}