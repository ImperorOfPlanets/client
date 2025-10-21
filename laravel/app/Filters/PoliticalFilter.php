<?php

namespace App\Filters;

use App\Models\Assistant\MessagesModel;
use App\Jobs\AI\ProcessLlmRequest;
use App\Models\Ai\AiRequest;
use App\Helpers\Ai\AiServiceLocator;
use Illuminate\Support\Facades\Log;

class PoliticalFilter extends Filter
{
    public function handle(MessagesModel $message): array
    {
        $text = trim($message->text);

        $this->sendDebugMessage($message, "Политический анализ сообщения", [
            'text' => $text
        ]);

        Log::info('Обработка политического фильтра', [
            'message_id' => $message->id,
            'text' => $text,
            'filter_id' => $this->getFilterId()
        ]);

        // Получаем настройки фильтра из конфигурации
        $filterConfig = $this->getFilterConfig();
        $prompt = $filterConfig['parameters']['prompt'] ?? '';

        if (empty($prompt)) {
            Log::error('Пустой промпт в политическом фильтре', [
                'filter_id' => $this->getFilterId(),
                'filter_config' => $filterConfig
            ]);
            return $this->createResponse(true, self::DECISION_CONTINUE);
        }

        // ИСПОЛЬЗУЕМ ТОЛЬКО POLZAAI (ID = 5)
        $polzaAiService = AiServiceLocator::getServiceById(5);

        if (!$polzaAiService) {
            Log::error('PolzaAI сервис недоступен для политического фильтра', [
                'service_id' => 5,
                'message_id' => $message->id
            ]);
            return $this->createResponse(true, self::DECISION_CONTINUE);
        }

        Log::info('Используем PolzaAI для политического анализа', [
            'message_id' => $message->id,
            'service_name' => $polzaAiService::getName()
        ]);

        // Создаем AI запрос с PolzaAI
        $aiRequestId = $this->createPoliticalAnalysisRequest($message, $text, $prompt, $polzaAiService);

        if ($aiRequestId) {
            $this->sendDebugMessage($message, "Создан AI запрос для политического анализа", [
                'ai_request_id' => $aiRequestId,
                'service' => 'PolzaAI'
            ]);

            ProcessLlmRequest::dispatch($aiRequestId);

            return [
                'status' => self::STATUS_PENDING,
                'ai_request_id' => $aiRequestId,
                'decision' => self::DECISION_WAIT_EXTERNAL,
                'filter_id' => $this->getFilterId(),
                'reason' => 'political_analysis_polzaai'
            ];
        }

        return $this->createResponse(true, self::DECISION_CONTINUE);
    }

    protected function createPoliticalAnalysisRequest(MessagesModel $message, string $text, string $prompt, $service): ?int
    {
        try {
            // Заменяем плейсхолдеры в промпте
            $processedPrompt = $this->processPrompt($prompt, $text, $message->info);

            $aiRequest = AiRequest::create([
                'service_id' => 5, // Жестко задаем ID PolzaAI
                'request_data' => [
                    'prompt' => $processedPrompt,
                    'original_message' => $text,
                    'response_format' => 'json',
                    'temperature' => 0.3,
                    'max_tokens' => 500
                ],
                'metadata' => [
                    'message_id' => $message->id,
                    'filter_id' => $this->getFilterId(),
                    'filter_type' => 'political',
                    'service_forced' => 'PolzaAI', // Явно указываем, что используем PolzaAI
                    'user_id' => $message->info['from'] ?? null,
                    'is_group' => $message->info['is_group'] ?? false,
                    'message_info' => $message->info,
                    'processing_callback' => [
                        'type' => 'filter_completion',
                        'filter_class' => self::class,
                        'method' => 'processPoliticalResponse'
                    ]
                ],
                'status' => 'pending'
            ]);

            Log::info('Создан AI запрос для политического анализа через PolzaAI', [
                'ai_request_id' => $aiRequest->id,
                'message_id' => $message->id,
                'service' => $service::getName(),
                'service_id' => 5
            ]);

            return $aiRequest->id;

        } catch (\Throwable $e) {
            Log::error("Ошибка создания AI запроса для политического фильтра: " . $e->getMessage(), [
                'message_id' => $message->id,
                'filter_id' => $this->getFilterId(),
                'service' => 'PolzaAI'
            ]);
            return null;
        }
    }

    protected function processPrompt(string $prompt, string $text, array $userInfo): string
    {
        $replacements = [
            '{{message}}' => $text,
            '{{user_name}}' => $userInfo['name'] ?? 'Пользователь',
            '{{user_id}}' => $userInfo['from'] ?? 'неизвестен',
            '{{is_group}}' => $userInfo['is_group'] ? 'групповой чат' : 'личный чат'
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $prompt);
    }

    public static function processPoliticalResponse(int $aiRequestId, array $response): void
    {
        $aiRequest = AiRequest::find($aiRequestId);
        if (!$aiRequest) {
            Log::error("AI request not found for political filter", ['ai_request_id' => $aiRequestId]);
            return;
        }

        $messageId = $aiRequest->metadata['message_id'] ?? null;
        $message = MessagesModel::find($messageId);
        if (!$message) {
            Log::error("Message not found for political AI request", ['ai_request_id' => $aiRequestId]);
            $aiRequest->update(['status' => 'completed']);
            return;
        }

        $analysisResult = self::extractPoliticalAnalysis($response);

        Log::info('Политический анализ PolzaAI завершен', [
            'message_id' => $message->id,
            'ai_request_id' => $aiRequestId,
            'has_result' => !empty($analysisResult),
            'service' => 'PolzaAI'
        ]);

        $aiRequest->update(['status' => 'completed']);
    }

    protected static function extractPoliticalAnalysis(array $response): ?array
    {
        $responseData = $response['response_data'] ?? $response;
        
        $text = 
            $responseData['text'] ?? 
            $responseData['response'] ?? 
            $responseData['data'] ?? 
            $responseData['choices'][0]['message']['content'] ?? 
            $responseData['choices'][0]['text'] ?? 
            '';

        $cleanText = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/m', '', $text);
        $cleanText = trim($cleanText);

        try {
            return json_decode($cleanText, true);
        } catch (\Throwable $e) {
            Log::warning('Не удалось декодировать JSON политического анализа от PolzaAI', [
                'text' => $cleanText,
                'service' => 'PolzaAI'
            ]);
            return null;
        }
    }

    public function processSavedData(MessagesModel $message, array $result): array
    {
        $this->sendDebugMessage($message, "Обработка результатов политического анализа", [
            'result_keys' => array_keys($result)
        ]);

        Log::info('Обработка сохраненных данных в политическом фильтре (PolzaAI)', [
            'message_id' => $message->id,
            'result_keys' => array_keys($result),
            'filter_id' => $this->getFilterId(),
            'service' => 'PolzaAI'
        ]);

        try {
            $analysisResult = self::extractPoliticalAnalysis($result);

            if ($analysisResult) {
                $isApproved = $analysisResult['approved'] ?? true;
                $confidence = $analysisResult['confidence'] ?? 0.5;
                $riskLevel = $analysisResult['risk_level'] ?? 'low';

                Log::info('Политический анализ PolzaAI завершен из сохраненных данных', [
                    'message_id' => $message->id,
                    'approved' => $isApproved,
                    'confidence' => $confidence,
                    'risk_level' => $riskLevel,
                    'service' => 'PolzaAI'
                ]);

                // Логика принятия решения на основе анализа
                if (!$isApproved || $riskLevel === 'high') {
                    return $this->createResponse(false, self::DECISION_REJECT, self::STATUS_COMPLETED, [
                        'reason' => 'political_content_rejected',
                        'confidence' => $confidence,
                        'risk_level' => $riskLevel,
                        'service' => 'PolzaAI'
                    ]);
                }

                return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED, [
                    'reason' => 'political_content_approved',
                    'confidence' => $confidence,
                    'risk_level' => $riskLevel,
                    'service' => 'PolzaAI'
                ]);
            }

            // Если анализ не удался, продолжаем обработку для безопасности
            return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED, [
                'reason' => 'political_analysis_failed_continue',
                'service' => 'PolzaAI'
            ]);

        } catch (\Throwable $e) {
            Log::error('Ошибка обработки сохраненных данных политического фильтра PolzaAI', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
                'service' => 'PolzaAI'
            ]);

            // При ошибке продолжаем обработку для безопасности
            return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED, [
                'reason' => 'political_analysis_error_continue',
                'service' => 'PolzaAI'
            ]);
        }
    }

    /**
     * Получение структуры параметров для PoliticalFilter
     */
    public function getParametersStructure(): array
    {
        $parentStructure = parent::getParametersStructure();
        
        return array_merge($parentStructure, [
            'prompt' => [
                'type' => 'textarea',
                'label' => 'Промпт для анализа',
                'description' => 'Системный промпт для политического анализа',
                'default' => 'Проанализируй текст на наличие политического контента...',
                'required' => true,
                'rows' => 6
            ],
            'confidence_threshold' => [
                'type' => 'number',
                'label' => 'Порог уверенности',
                'description' => 'Минимальная уверенность для отклонения контента (0.1-1.0)',
                'default' => 0.7,
                'min' => 0.1,
                'max' => 1.0,
                'step' => 0.1
            ],
            'auto_reject_high_risk' => [
                'type' => 'boolean',
                'label' => 'Авто-отклонение высокого риска',
                'description' => 'Автоматически отклонять сообщения с высоким уровнем риска',
                'default' => true
            ],
            'notify_on_reject' => [
                'type' => 'boolean',
                'label' => 'Уведомлять об отклонении',
                'description' => 'Отправлять пользователю уведомление при отклонении',
                'default' => true
            ]
        ]);
    }
}