<?php

namespace App\Filters;

use App\Models\Assistant\MessagesModel;
use App\Helpers\Socials\SocialInterface;
use App\Jobs\Assistant\Messages\Download;
use App\Jobs\Assistant\Messages\SendVoice;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class Voice extends Filter
{
    private const MAX_DURATION = 30;
    private const KB_PER_SECOND = 100;

    public function getRequiredVariables(): array
    {
        return [
            // Метаданные голоса
            'message_type' => [
                'type' => 'string',
                'source' => 'info',
                'required' => true,
                'validation' => 'required|in:voice,audio'
            ],
            'voice_file_id' => [
                'type' => 'string',
                'source' => 'info',
                'required' => true,
                'validation' => 'required|min:1'
            ],
            
            // Технические ограничения
            'max_duration' => [
                'type' => 'integer',
                'source' => 'parameter',
                'required' => false,
                'default' => 30,
                'validation' => 'min:5|max:300'
            ],
            'max_file_size' => [
                'type' => 'integer',
                'source' => 'system', // автоматически рассчитывается
                'required' => false
            ],
            
            // Обработка
            'raw_external_data' => [
                'type' => 'array',
                'source' => 'info',
                'required' => false,
                'validation' => function($value) {
                    // Может быть null или массив с определенными полями
                    return $value === null || 
                        (is_array($value) && isset($value['totext']));
                }
            ],
            
            // Системные
            'social_instance' => [
                'type' => 'object',
                'source' => 'system', // self::getSocialInstance($message)
                'required' => true
            ]
        ];
    }

    public function handle(MessagesModel $message): array
    {
        $this->sendDebugMessage($message, "Обработка голосового сообщения", [
            'message_type' => $message->info['message_type'] ?? '',
            'has_raw_data' => isset($message->info['raw_external_data'])
        ]);

        Log::info('Фильтр голосовых сообщений запущен', [
            'message_id' => $message->id,
            'message_type' => $message->info['message_type'] ?? '',
            'current_text' => $message->text ?? '',
            'has_raw_external_data' => isset($message->info['raw_external_data']),
            'filter_id' => $this->getFilterId(),
            'filter_name' => $this->getFilterName()
        ]);

        if ($this->isVoiceAlreadyProcessed($message)) {
            $this->sendDebugMessage($message, "Голосовое сообщение уже обработано");
            
            Log::info('Голосовое сообщение уже обработано, продолжаем цепочку', [
                'message_id' => $message->id,
                'current_text' => $message->text
            ]);
            return $this->createResponse(true, self::DECISION_CONTINUE);
        }

        if ($this->hasVoiceDataToProcess($message)) {
            $this->sendDebugMessage($message, "Обнаружены сырые данные для обработки", [
                'raw_data_keys' => array_keys($message->info['raw_external_data'] ?? [])
            ]);
            
            Log::info('Обнаружены сырые данные для обработки', [
                'message_id' => $message->id,
                'raw_data_keys' => array_keys($message->info['raw_external_data'] ?? [])
            ]);
            return $this->processExternalVoiceData($message);
        }

        $validationResult = $this->validateVoiceMessage($message);

        if (!isset($validationResult['valid']) || !isset($validationResult['response'])) {
            Log::error('Некорректная структура результата валидации', [
                'message_id' => $message->id,
                'validation_result' => $validationResult
            ]);

            return $this->createResponse(false, self::DECISION_REJECT, self::STATUS_COMPLETED, [
                'reason' => 'invalid_validation_result',
                'error_message' => 'Ошибка валидации голосового сообщения'
            ]);
        }

        if (!$validationResult['valid']) {
            Log::warning('Голосовое сообщение не прошло валидацию', [
                'message_id' => $message->id,
                'validation_result' => $validationResult
            ]);
            return $validationResult['response'];
        }

        try {
            $this->sendProcessingNotification($message);
            $this->startAsyncVoiceProcessing($message);

            Log::info('Запущена асинхронная обработка голосового сообщения через Jobs', [
                'message_id' => $message->id
            ]);

            return $this->createResponse(true, self::DECISION_WAIT_EXTERNAL, self::STATUS_PENDING, [
                'reason' => 'voice_processing_async',
                'external_id' => $message->id,
                'processing_type' => 'voice_recognition',
                'jobs_chain' => ['Download', 'SendVoice']
            ]);
        } catch (\Throwable $e) {
            Log::error('Ошибка запуска обработки голосового сообщения', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);

            $this->sendErrorNotification($message, $e->getMessage());

            return $this->createResponse(false, self::DECISION_REJECT, self::STATUS_COMPLETED, [
                'reason' => 'voice_processing_error',
                'error_message' => 'Ошибка обработки голосового сообщения: ' . $e->getMessage()
            ]);
        }
    }

    public function processSavedData(MessagesModel $message, array $result): array
    {
        Log::info('Обработка сохраненных данных в фильтре Voice', [
            'message_id' => $message->id,
            'result_keys' => array_keys($result)
        ]);

        // Основная логика обработки голоса...
        $recognizedText = $this->extractRecognizedText($result);

        if (empty($recognizedText)) {
            Log::error('Пустой распознанный текст в Voice::processSavedData', [
                'message_id' => $message->id,
                'result_data' => $result
            ]);
            return $this->createResponse(false, self::DECISION_REJECT, self::STATUS_COMPLETED);
        }

        $this->updateMessageWithRecognizedText($message, $recognizedText, $result);
        $this->sendRecognitionNotification($message, $recognizedText);

        return $this->createResponse(true, self::DECISION_CONTINUE);
    }

    protected function hasVoiceDataToProcess(MessagesModel $message): bool
    {
        $rawData = $message->getRawExternalData();

        Log::debug('Проверка наличия голосовых данных для обработки', [
            'message_id' => $message->id,
            'raw_external_data_present' => !empty($rawData),
            'raw_external_data_keys' => array_keys($rawData)
        ]);

        $recognizedText = $this->extractRecognizedText($rawData);

        if (empty($recognizedText)) {
            Log::warning('Нет распознанного текста в raw_external_data', [
                'message_id' => $message->id,
                'raw_external_data' => $rawData
            ]);
            return false;
        }

        return true;
    }

    protected function processExternalVoiceData(MessagesModel $message): array
    {
        $rawData = $message->getRawExternalData();
        $recognizedText = $this->extractRecognizedText($rawData);

        $this->sendDebugMessage($message, "Обработка внешних голосовых данных", [
            'recognized_text' => $recognizedText,
            'raw_data_keys' => array_keys($rawData)
        ]);

        if (empty($recognizedText)) {
            Log::error('Пустой распознанный текст в обработке внешних данных', [
                'message_id' => $message->id,
                'raw_data' => $rawData
            ]);

            return $this->createResponse(false, self::DECISION_REJECT, self::STATUS_COMPLETED, [
                'reason' => 'empty_recognized_text_external',
                'error_message' => 'Не удалось извлечь текст из внешних данных'
            ]);
        }

        $this->updateMessageWithRecognizedText($message, $recognizedText, $rawData);
        $this->sendRecognitionNotification($message, $recognizedText);

        Log::info('Внешние голосовые данные успешно обработаны', [
            'message_id' => $message->id,
            'text_length' => mb_strlen($recognizedText)
        ]);

        return $this->createResponse(true, self::DECISION_CONTINUE);
    }

    protected function extractRecognizedText(array $rawData): string
    {
        Log::debug('Извлечение текста из raw_external_data', [
            'keys' => array_keys($rawData)
        ]);

        $textSources = [
            'totext',
            'text',
            'transcription',
            'translate',
            'result.text'
        ];

        foreach ($textSources as $source) {
            if (strpos($source, '.') !== false) {
                $keys = explode('.', $source);
                $value = $rawData;
                foreach ($keys as $key) {
                    if (!isset($value[$key])) {
                        $value = null;
                        break;
                    }
                    $value = $value[$key];
                }
                if (!empty($text = trim($value ?? ''))) {
                    Log::debug("Извлечён {$source}");
                    return $text;
                }
            } else {
                if (!empty($text = trim($rawData[$source] ?? ''))) {
                    Log::debug("Извлечён {$source}");
                    return $text;
                }
            }
        }

        return '';
    }

    protected function updateMessageWithRecognizedText(MessagesModel $message, string $recognizedText, array $rawData = []): void
    {
        try {
            $message->text = $recognizedText;

            $info = $message->info ?? [];
            $info['voice_processing'] = [
                'status' => 'completed',
                'completed_at' => now()->toISOString(),
                'recognized_text' => $recognizedText,
                'raw_data_source' => $this->getDataSource($rawData)
            ];

            $message->info = $info;
            $message->save();

            Log::info('Сообщение обновлено с распознанным текстом', [
                'message_id' => $message->id,
                'text_preview' => mb_substr($recognizedText, 0, 50) . '...',
                'text_length' => mb_strlen($recognizedText)
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка обновления сообщения с распознанным текстом', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    protected function getDataSource(array $rawData): string
    {
        if (isset($rawData['totext'])) return 'totext';
        if (isset($rawData['text'])) return 'text';
        if (isset($rawData['translate'])) return 'translate';
        if (isset($rawData['transcription'])) return 'transcription';
        return 'unknown';
    }

    protected function isVoiceAlreadyProcessed(MessagesModel $message): bool
    {
        $info = $message->info ?? [];

        if (!empty($message->text) && $message->text !== '') {
            Log::debug('Голос уже обработан: текст уже существует в message->text', [
                'message_id' => $message->id,
                'text_length' => strlen($message->text)
            ]);
            return true;
        }

        if (isset($info['voice_processing']['status']) &&
            $info['voice_processing']['status'] === 'completed') {
            Log::debug('Голос уже обработан: статус completed', [
                'message_id' => $message->id
            ]);
            return true;
        }

        if (isset($info['voice_processing']['jobs_chain_started']) &&
            $info['voice_processing']['jobs_chain_started'] === true &&
            isset($info['voice_processing']['expected_callback']) &&
            $info['voice_processing']['expected_callback'] === true) {
            Log::debug('Голос уже в обработке: ожидается callback', [
                'message_id' => $message->id
            ]);
            return true;
        }

        if (isset($info['raw_external_data']) &&
            (isset($info['raw_external_data']['totext']) || isset($info['raw_external_data']['text']))) {
            Log::debug('Голос НЕ обработан: существуют сырые данные для обработки', [
                'message_id' => $message->id
            ]);
            return false;
        }

        return false;
    }

    protected function validateVoiceMessage(MessagesModel $message): array
    {
        $info = $message->info;
        $socId = $message->soc;

        $social = self::getSocialInstance($message);
        if (!$social) {
            Log::warning('Класс социальной сети не найден', [
                'message_id' => $message->id,
                'soc_id' => $socId
            ]);

            return [
                'valid' => false,
                'response' => $this->createResponse(false, self::DECISION_REJECT, self::STATUS_COMPLETED, [
                    'reason' => 'social_class_not_found',
                    'error_message' => 'Класс социальной сети не найден'
                ])
            ];
        }

        try {
            $voiceParams = $info;

            $maxFileSize = $this->estimateMaxSizeForDuration(self::MAX_DURATION);

            $duration = null;
            if ($social->checkGetVoiceMessageDuration($voiceParams)) {
                $duration = $social->getVoiceMessageDuration($voiceParams);
            }

            $fileSize = null;
            if ($social->checkGetVoiceMessageFileSize($voiceParams)) {
                $fileSize = $social->getVoiceMessageFileSize($voiceParams);
            }

            Log::debug('Параметры голосового сообщения', [
                'message_id' => $message->id,
                'duration' => $duration,
                'file_size' => $fileSize,
                'max_duration' => self::MAX_DURATION,
                'max_file_size' => $maxFileSize
            ]);

            if ($duration !== null && $duration > self::MAX_DURATION) {
                Log::warning('Голосовое сообщение слишком длинное', [
                    'message_id' => $message->id,
                    'duration' => $duration,
                    'max_duration' => self::MAX_DURATION
                ]);

                return [
                    'valid' => false,
                    'response' => $this->createResponse(false, self::DECISION_REJECT, self::STATUS_COMPLETED, [
                        'reason' => 'voice_too_long',
                        'error_message' => "Голосовое сообщение превышает " . self::MAX_DURATION . " секунд"
                    ])
                ];
            }

            if ($fileSize !== null && $fileSize > $maxFileSize) {
                Log::warning('Файл голосового сообщения слишком большой', [
                    'message_id' => $message->id,
                    'file_size' => $fileSize,
                    'max_file_size' => $maxFileSize
                ]);

                return [
                    'valid' => false,
                    'response' => $this->createResponse(false, self::DECISION_REJECT, self::STATUS_COMPLETED, [
                        'reason' => 'voice_file_too_large',
                        'error_message' => 'Размер файла голосового сообщения слишком большой'
                    ])
                ];
            }

            Log::debug('Голосовое сообщение прошло валидацию', [
                'message_id' => $message->id,
                'duration' => $duration,
                'file_size' => $fileSize
            ]);

            return [
                'valid' => true,
                'response' => $this->createResponse(true, self::DECISION_CONTINUE)
            ];
        } catch (\Throwable $e) {
            Log::error('Ошибка при валидации голосового сообщения', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'valid' => false,
                'response' => $this->createResponse(false, self::DECISION_REJECT, self::STATUS_COMPLETED, [
                    'reason' => 'validation_error',
                    'error_message' => 'Ошибка проверки голосового сообщения: ' . $e->getMessage()
                ])
            ];
        }
    }

    protected function startAsyncVoiceProcessing(MessagesModel $message): void
    {
        $this->markVoiceAsProcessing($message);

        Bus::chain([
            new Download(['message_id' => $message->id]),
            new SendVoice(['message_id' => $message->id]),
        ])->dispatch();

        Log::info('Цепочка Jobs для обработки голоса запущена', [
            'message_id' => $message->id,
            'jobs' => ['Download', 'SendVoice']
        ]);
    }

    protected function markVoiceAsProcessing(MessagesModel $message): void
    {
        $info = $message->info ?? [];
        $info['voice_processing'] = [
            'status' => 'processing',
            'started_at' => now()->toISOString(),
            'filter_id' => $this->getFilterId(),
            'jobs_chain_started' => true,
            'expected_callback' => true
        ];

        $message->info = $info;
        $message->save();
    }

    protected function sendProcessingNotification(MessagesModel $message): void
    {
        try {
            self::sendMessage($message, '🫠 Ваше голосовое сообщение получено и отправлено на обработку. Пожалуйста, подождите...');
        } catch (\Throwable $e) {
            Log::error('Ошибка отправки уведомления об обработке', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function sendRecognitionNotification(MessagesModel $message, string $recognizedText): void
    {
        try {
            $notification = "✅ Ваше голосовое сообщение распознано!\n\n";
            $notification .= "📝 Текст: \"{$recognizedText}\"\n\n";
            $notification .= "⏳ Продолжаю обработку запроса...";

            self::sendMessage($message, $notification);

            Log::info('Уведомление о распознавании отправлено', [
                'message_id' => $message->id,
                'chat_id' => $message->chat_id
            ]);
        } catch (\Throwable $e) {
            Log::error('Ошибка отправки уведомления о распознавании', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function estimateMaxSizeForDuration(int $seconds): int
    {
        return $seconds * self::KB_PER_SECOND * 1024;
    }
}