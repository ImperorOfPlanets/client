<?php

namespace App\Filters;

use App\Models\Assistant\MessagesModel;
use App\Jobs\AI\ProcessLlmRequest;
use App\Models\Ai\AiRequest;
use App\Helpers\Ai\AiServiceLocator;
use Illuminate\Support\Facades\Log;

class VectorAnswer extends Filter
{
    public function handle(MessagesModel $message): array
    {
        $text = trim($message->text);

        Log::info('Обработка векторного ответа', [
            'message_id' => $message->id,
            'text' => $text,
            'filter_id' => $this->getFilterId()
        ]);

        $vectorResults = $this->getVectorSearchResults($message);

        if (empty($vectorResults)) {
            Log::info('Нет результатов векторного поиска для ответа', [
                'message_id' => $message->id
            ]);

            return $this->createResponse(true, self::DECISION_CONTINUE);
        }

        $aiRequestId = $this->createVectorAnswerRequest($message, $text, $vectorResults);

        if ($aiRequestId) {
            ProcessLlmRequest::dispatch($aiRequestId);

            return [
                'status' => self::STATUS_PENDING,
                'ai_request_id' => $aiRequestId,
                'decision' => self::DECISION_WAIT_EXTERNAL,
                'filter_id' => $this->getFilterId(),
                'reason' => 'vector_answer_generation'
            ];
        }

        return $this->createResponse(true, self::DECISION_CONTINUE);
    }

    protected function getVectorSearchResults(MessagesModel $message): array
    {
        $info = $message->info ?? [];

        $vectorResults =
            $info['vector_search_results'] ??
            $info['vector_search'] ??
            $info['embedding_results'] ??
            [];

        Log::debug('Результаты векторного поиска', [
            'message_id' => $message->id,
            'results_count' => count($vectorResults),
            'source' => array_keys($info)
        ]);

        return $vectorResults;
    }

    protected function createVectorAnswerRequest(MessagesModel $message, string $query, array $vectorResults): ?int
    {
        $services = AiServiceLocator::getAllActiveServices();

        if (empty($services)) {
            Log::error('Нет активных AI сервисов для генерации ответа');
            return null;
        }

        $prompt = $this->generateVectorAnswerPrompt($query, $vectorResults);

        try {
            $aiRequest = AiRequest::create([
                'service_id' => $services[0]->id,
                'request_data' => [
                    'prompt' => $prompt,
                    'original_query' => $query,
                    'vector_results_count' => count($vectorResults),
                    'response_format' => 'text',
                    'temperature' => 0.7,
                    'max_tokens' => 1000
                ],
                'metadata' => [
                    'message_id' => $message->id,
                    'filter_id' => $this->getFilterId(),
                    'user_id' => $message->info['from'] ?? null,
                    'is_group' => $message->info['is_group'] ?? false,
                    'message_info' => $message->info,
                    'vector_results_count' => count($vectorResults),
                    'processing_callback' => [
                        'type' => 'filter_completion',
                        'filter_class' => self::class,
                        'method' => 'processVectorAnswerResponse'
                    ]
                ],
                'status' => 'pending'
            ]);

            Log::info('Создан AI запрос для векторного ответа', [
                'ai_request_id' => $aiRequest->id,
                'message_id' => $message->id,
                'vector_results_count' => count($vectorResults)
            ]);

            return $aiRequest->id;
        } catch (\Throwable $e) {
            Log::error("Ошибка создания AI запроса для векторного ответа: " . $e->getMessage(), [
                'message_id' => $message->id,
                'filter_id' => $this->getFilterId()
            ]);
            return null;
        }
    }

    protected function generateVectorAnswerPrompt(string $query, array $vectorResults): string
    {
        $contextText = $this->prepareContextFromVectorResults($vectorResults);

        $prompt = "Ты - полезный AI-ассистент. Используй предоставленную информацию из базы знаний, чтобы дать точный и полезный ответ на вопрос пользователя.\n\n";

        $prompt .= "КОНТЕКСТ ИЗ БАЗЫ ЗНАНИЙ:\n";
        $prompt .= "{$contextText}\n\n";

        $prompt .= "ВОПРОС ПОЛЬЗОВАТЕЛЯ: {$query}\n\n";

        $prompt .= "ИНСТРУКЦИИ:\n";
        $prompt .= "1. Основывай ответ ТОЛЬКО на предоставленном контексте\n";
        $prompt .= "2. Если в контексте нет информации для ответа, вежливо сообщи об этом\n";
        $prompt .= "3. Будь точным, полезным и дружелюбным\n";
        $prompt .= "4. Структурируй ответ, если это уместно\n";
        $prompt .= "5. Не придумывай информацию, которой нет в контексте\n";
        $prompt .= "6. Если информация в контексте противоречива, укажи на это\n\n";

        $prompt .= "ОТВЕТ:";

        return $prompt;
    }

    protected function prepareContextFromVectorResults(array $vectorResults): string
    {
        $context = [];

        foreach ($vectorResults as $index => $result) {
            $content = $this->extractContentFromVectorResult($result);
            $score = $result['score'] ?? $result['distance'] ?? null;

            if ($content) {
                $contextPart = "【Источник {$index}";
                if ($score !== null) {
                    $contextPart .= " (релевантность: " . round($score, 3) . ")";
                }
                $contextPart .= "】\n{$content}\n";

                $context[] = $contextPart;
            }
        }

        return implode("\n", $context);
    }

    protected function extractContentFromVectorResult(array $result): string
    {
        return
            $result['payload']['content'] ??
            $result['content'] ??
            $result['text'] ??
            $result['data'] ??
            (is_string($result) ? $result : '');
    }

    public static function processVectorAnswerResponse(int $aiRequestId, array $response): void
    {
        $aiRequest = AiRequest::find($aiRequestId);
        if (!$aiRequest) {
            Log::error("AI request not found for vector answer", ['ai_request_id' => $aiRequestId]);
            return;
        }

        $messageId = $aiRequest->metadata['message_id'] ?? null;
        $message = MessagesModel::find($messageId);
        if (!$message) {
            Log::error("Message not found for vector answer AI request", ['ai_request_id' => $aiRequestId]);
            $aiRequest->update(['status' => 'completed']);
            return;
        }

        $answer = self::extractAnswerFromAiResponse($response);

        if ($answer) {
            // Отправляем ответ через общий метод
            self::sendMessage($message, $answer, ['reply_for' => $message->info['message_id'] ?? null]);

            Log::info('Векторный ответ отправлен пользователю', [
                'message_id' => $message->id,
                'answer_length' => strlen($answer),
                'ai_request_id' => $aiRequestId
            ]);
        } else {
            Log::warning('Не удалось извлечь ответ из AI response', [
                'message_id' => $message->id,
                'response' => $response
            ]);

            self::sendErrorNotification($message, 'Ошибка генерации ответа на основе базы знаний');
        }

        $aiRequest->update(['status' => 'completed']);

        $message->update([
            'status' => 1,
            'info->processed_with_vector_answer' => true,
            'info->vector_answer_generated_at' => now()->toISOString()
        ]);
    }

    protected static function extractAnswerFromAiResponse(array $response): string
    {
        $responseData = $response['response_data'] ?? $response;

        $text =
            $responseData['text'] ??
            $responseData['response'] ??
            $responseData['data'] ??
            $responseData['choices'][0]['message']['content'] ??
            $responseData['choices'][0]['text'] ??
            '';

        $cleanText = preg_replace('/^```\w*\s*|\s*```$/m', '', $text);
        $cleanText = trim($cleanText);

        return $cleanText;
    }

    public function processSavedData(MessagesModel $message, array $result): array
    {
        Log::info('Обработка сохраненных данных в фильтре VectorAnswer', [
            'message_id' => $message->id,
            'result_keys' => array_keys($result),
            'filter_id' => $this->getFilterId()
        ]);

        try {
            $answer = self::extractAnswerFromAiResponse($result);

            if ($answer) {
                self::sendMessage($message, $answer, ['reply_for' => $message->info['message_id'] ?? null]);

                $message->update([
                    'status' => 1,
                    'info->processed_with_vector_answer' => true,
                    'info->vector_answer_generated_at' => now()->toISOString()
                ]);

                return $this->createResponse(false, self::DECISION_SKIP, self::STATUS_COMPLETED, [
                    'reason' => 'vector_answer_from_saved_data',
                    'answer_length' => strlen($answer)
                ]);
            }

            return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED, [
                'reason' => 'no_vector_answer_in_saved_data'
            ]);
        } catch (\Throwable $e) {
            Log::error('Ошибка обработки сохраненных данных VectorAnswer', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);

            return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED, [
                'reason' => 'vector_answer_processing_error',
                'error_message' => $e->getMessage()
            ]);
        }
    }
}
