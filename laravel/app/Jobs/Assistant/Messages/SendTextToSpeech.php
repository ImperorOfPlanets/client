<?php

namespace App\Jobs\Assistant\Messages;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Assistant\MessagesModel;
use App\Models\Socials\SocialsModel;

class SendTextToSpeech implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $params;
    public $message;
    public $initializedSocials = [];

    protected $voiceServers = [
        'https://voice1.example.com',
        'https://voice2.example.com'
    ];

    protected function getVoicePresets(): array
    {
        return [
            'male_deep' => [
                'speed' => 0.9,
                'pitch' => 0.8,
                'energy' => 1.1,
                'model' => 'ru_vits'
            ],
            'female_high' => [
                'speed' => 1.1,
                'pitch' => 1.2,
                'energy' => 0.9,
                'model' => 'ru_vits'
            ],
            'robot' => [
                'speed' => 0.8,
                'pitch' => 1.5,
                'energy' => 1.3,
                'model' => 'ru_vits'
            ],
            'child' => [
                'speed' => 1.2,
                'pitch' => 1.4,
                'energy' => 1.0,
                'model' => 'ru_vits'
            ]
        ];
    }

    protected function applyVoicePreset(string $presetName): array
    {
        $presets = $this->getVoicePresets();
        return $presets[$presetName] ?? $presets['male_deep'];
    }

    public function __construct($params = null)
    {
        $this->params = $params;
        $projectName = env('PROJECTNAME', 'core');
        $localServer = "http://voice-tts-{$projectName}:5001";
        array_unshift($this->voiceServers, $localServer);

        Log::info('SendTextToSpeech job initialized', [
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

        $text = $this->params['text'] ?? $this->message->text;
        if (empty($text)) {
            $this->logAndNotify('no_text', 'Текст для синтеза не предоставлен');
            return;
        }

        // Ограничиваем длину текста
        if (strlen($text) > 1000) {
            $text = substr($text, 0, 1000);
            Log::warning('Text truncated to 1000 characters', [
                'message_id' => $this->message->id,
                'original_length' => strlen($this->params['text'] ?? $this->message->text)
            ]);
        }

        $server = $this->getAvailableServer();
        if (!$server) {
            $this->logAndNotify('no_server', 'Нет доступного TTS сервера');
            return;
        }

        try {
            $targetUrl = rtrim($server, '/') . '/speak';
            
            $requestData = [
                'text' => $text,
                'speaker_idx' => $this->params['speaker_idx'] ?? 0,
                'speed' => $this->params['speed'] ?? 1.0,
                'voice_params' => $this->params['voice_params'] ?? null,
                'model' => $this->params['model'] ?? 'ru_vits'  // Выбор модели
            ];

            // Если указаны расширенные параметры голоса
            if (isset($this->params['voice_params'])) {
                $requestData['voice_params'] = $this->params['voice_params'];
            }

            Log::info('Отправка запроса на TTS сервер с расширенными параметрами', [
                'message_id' => $this->message->id,
                'model' => $requestData['model'],
                'voice_params' => $requestData['voice_params'] ?? 'none'
            ]);

            $response = Http::timeout(60)
                ->withHeaders([
                    'Accept' => 'audio/wav',
                    'Content-Type' => 'application/json'
                ])
                ->post($targetUrl, $requestData);

            $statusCode = $response->status();
            $headers = $response->headers();

            Log::info('Ответ от TTS сервера получен', [
                'message_id' => $this->message->id,
                'status_code' => $statusCode,
                'content_type' => $headers['Content-Type'][0] ?? 'unknown',
                'content_length' => $headers['Content-Length'][0] ?? 'unknown'
            ]);

            if ($statusCode === 200 && 
                isset($headers['Content-Type']) && 
                str_contains($headers['Content-Type'][0], 'audio/wav')) {
                
                $this->handleSuccessfulTTS($response->body(), $headers);
                
            } else {
                $this->handleTtsError($response->body(), $statusCode);
            }

        } catch (\Exception $e) {
            Log::error('Ошибка при запросе к TTS серверу', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->logAndNotify('tts_error', 'Ошибка синтеза речи: ' . $e->getMessage());
        }
    }

    protected function handleSuccessfulTTS(string $audioData, array $headers): void
    {
        try {
            // Сохраняем аудиофайл
            $fileName = 'tts_' . $this->message->id . '_' . time() . '.wav';
            $filePath = storage_path('app/voice/' . $fileName);
            
            // Создаем директорию если не существует
            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }
            
            file_put_contents($filePath, $audioData);
            
            $fileSize = filesize($filePath);
            
            Log::info('Аудиофайл успешно сохранен', [
                'message_id' => $this->message->id,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'audio_data_size' => strlen($audioData)
            ]);

            // Отправляем голосовое сообщение
            $this->sendVoiceMessage($filePath, $fileName);

            // Сохраняем информацию о TTS
            $this->saveTtsResponse([
                'status' => 'success',
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'content_type' => $headers['Content-Type'][0] ?? 'audio/wav',
                'text_length' => strlen($this->params['text'] ?? $this->message->text),
                'speaker_idx' => $this->params['speaker_idx'] ?? 0,
                'speed' => $this->params['speed'] ?? 1.0
            ]);

            // Очищаем файл после отправки (опционально)
            // unlink($filePath);

        } catch (\Exception $e) {
            Log::error('Ошибка обработки TTS ответа', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage()
            ]);
            $this->logAndNotify('file_save_error', 'Ошибка сохранения аудиофайла: ' . $e->getMessage());
        }
    }

    protected function sendVoiceMessage(string $filePath, string $fileName): void
    {
        try {
            $social = $this->initializedSocials[$this->message->soc];
            
            // Проверяем поддерживает ли социальная сеть отправку голосовых сообщений
            if (method_exists($social, 'sendVoice')) {
                $social->sendVoice(
                    $this->message->chat_id,
                    $filePath,
                    $this->message->info
                );
            } elseif (method_exists($social, 'sendAudio')) {
                // Если нет метода sendVoice, пробуем sendAudio
                $social->sendAudio(
                    $this->message->chat_id,
                    $filePath,
                    $this->message->info
                );
            } else {
                // Если нет поддержки аудио, отправляем текстовое сообщение с информацией
                $social->sendMessage(
                    $this->message->chat_id,
                    "🎤 Текст озвучен: " . substr($this->params['text'] ?? $this->message->text, 0, 100) . "...",
                    $this->message->info
                );
                $this->logAndNotify('no_voice_support', 'Социальная сеть не поддерживает отправку голосовых сообщений');
                return;
            }

            Log::info('Голосовое сообщение успешно отправлено', [
                'message_id' => $this->message->id,
                'file_path' => $filePath
            ]);

            // Отправляем подтверждение пользователю
            $social->sendMessage(
                $this->message->chat_id,
                "✅ Текст успешно преобразован в голосовое сообщение!",
                $this->message->info
            );

        } catch (\Exception $e) {
            Log::error('Ошибка отправки голосового сообщения', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->logAndNotify('voice_send_error', 'Ошибка отправки голосового сообщения: ' . $e->getMessage());
        }
    }

    protected function handleTtsError(string $errorResponse, int $statusCode): void
    {
        try {
            $errorData = json_decode($errorResponse, true) ?? ['error' => $errorResponse];
            
            Log::error('TTS сервер вернул ошибку', [
                'message_id' => $this->message->id,
                'status_code' => $statusCode,
                'error_response' => $errorData
            ]);

            $errorMessage = $errorData['error'] ?? 'Неизвестная ошибка TTS сервера';
            
            $this->saveTtsResponse([
                'status' => 'error',
                'error' => $errorMessage,
                'status_code' => $statusCode,
                'error_response' => $errorData
            ]);

            $this->logAndNotify('tts_server_error', "Ошибка TTS сервера: {$errorMessage}");

        } catch (\Exception $e) {
            Log::error('Ошибка обработки TTS ошибки', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function saveTtsResponse(array $responseData): void
    {
        try {
            $info = $this->message->info;
            $info['tts_response'] = array_merge([
                'timestamp' => now()->toISOString(),
                'server' => $this->getAvailableServer(),
                'text' => $this->params['text'] ?? $this->message->text
            ], $responseData);

            $this->message->info = $info;
            $this->message->save();

            Log::info('TTS ответ сохранен', [
                'message_id' => $this->message->id,
                'status' => $responseData['status'] ?? 'unknown'
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка сохранения TTS ответа', [
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
                    // Дополнительно проверяем доступность TTS
                    $ttsStatusUrl = rtrim($server, '/') . '/tts/status';
                    $ttsResponse = Http::timeout(2)->get($ttsStatusUrl);
                    
                    if ($ttsResponse->ok() && ($ttsData = $ttsResponse->json())) {
                        if ($ttsData['tts_available'] ?? false) {
                            return $server;
                        }
                    }
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
            $info['tts_error'] = $logMessage;
            $info['tts_error_timestamp'] = now()->toISOString();
            $info['tts_error_code'] = $errorCode;
            $this->message->info = $info;
            $this->message->save();
        }

        $userMessage = match ($errorCode) {
            'no_text' => "Текст для синтеза речи не предоставлен.",
            'no_server' => "Сервер синтеза речи недоступен. Попробуйте позже.",
            'tts_error' => "Произошла ошибка при синтезе речи. Попробуйте снова.",
            'tts_server_error' => "Сервер синтеза речи вернул ошибку. Попробуйте позже.",
            'file_save_error' => "Ошибка сохранения аудиофайла.",
            'voice_send_error' => "Ошибка отправки голосового сообщения.",
            'no_voice_support' => "Данная социальная сеть не поддерживает отправку голосовых сообщений.",
            'social_init' => "Ошибка обработки социальной сети. Обратитесь в поддержку.",
            default => "Произошла неизвестная ошибка при синтезе речи."
        };

        if (isset($this->initializedSocials[$this->message->soc])) {
            try {
                $this->initializedSocials[$this->message->soc]->sendMessage(
                    $this->message->chat_id,
                    $userMessage,
                    $this->message->info
                );
            } catch (\Exception $e) {
                Log::error('Ошибка отправки уведомления об ошибке TTS', [
                    'message_id' => $this->message->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}