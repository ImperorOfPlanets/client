<?php

namespace App\Filters;

use App\Models\Assistant\MessagesModel;
use App\Jobs\AI\ProcessLlmRequest;
use App\Models\Ai\AiRequest;
use App\Helpers\Ai\AiServiceLocator;
use App\Helpers\Assistant\CommandProcessor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Commands extends Filter
{
    protected array $commands = [];

    public function __construct()
    {
        parent::__construct();
        $this->loadCommands();

        Log::info('Commands filter initialized', [
            'filter_id' => $this->getFilterId(),
            'filter_name' => $this->getFilterName(),
            'commands_count' => count($this->commands)
        ]);
    }

    protected function loadCommands(): void
    {
        if (!Storage::disk('local')->exists('commands/keywords.json')) {
            Log::warning('Keywords file not found');
            return;
        }

        $json = Storage::disk('local')->get('commands/keywords.json');
        $this->commands = json_decode($json, true) ?? [];

        Log::info('Commands loaded', ['count' => count($this->commands)]);
    }

    public function match(string $text)
    {
        $textLower = mb_strtolower(trim($text));
        $matchedIds = [];

        foreach ($this->commands as $cmd) {
            $keywords = array_map('trim', explode(',', $cmd['keywords'] ?? ''));
            foreach ($keywords as $word) {
                if ($word === '') continue;
                if (mb_strtolower($word) === $textLower) {
                    $matchedIds[] = $cmd['id'];
                    break;
                }
            }
        }

        return !empty($matchedIds) ? $matchedIds : false;
    }

    protected function generatePrompt(string $userInput): string
    {
        $prompt = "Проанализируй, является ли текст пользователя командой.\n\n";
        $prompt .= "Доступные команды и их ключевые слова:\n";

        foreach ($this->commands as $cmd) {
            $prompt .= "- ID: {$cmd['id']}, Ключевые слова: {$cmd['keywords']}\n";
        }

        $prompt .= "\nТекст пользователя: \"$userInput\"\n\n";
        $prompt .= "Ответь ТОЛЬКО в формате JSON без какихких пояснений:\n";
        $prompt .= "{\n";
        $prompt .= "  \"is_command\": boolean,\n";
        $prompt .= "  \"command_id\": integer|null,\n";
        $prompt .= "  \"found_keyword\": string|null,\n";
        $prompt .= "  \"found_in_part\": string|null,\n";
        $prompt .= "  \"confidence\": float|null\n";
        $prompt .= "}\n\n";
        $prompt .= "Правила анализа:\n";
        $prompt .= "1. Учти возможность опечаток, фонетических и визуальных похожих слов\n";
        $prompt .= "2. Если слово похоже на ключевое считай это совпадением\n";
        $prompt .= "3. Для команд укажи уверенность от 0.1 до 1.0 (1.0 - точное совпадение)\n";
        $prompt .= "4. При уверенности ниже 0.7, не считай текст командой\n\n";
        $prompt .= "ПРИМЕРЫ:\n";
        $prompt .= "- Точное совпадение: {\"is_command\": true, \"command_id\": 1, \"found_keyword\": \"босс\", \"found_in_part\": \"босс\", \"confidence\": 1.0}\n";
        $prompt .= "- С опечаткой: {\"is_command\": true, \"command_id\": 1, \"found_keyword\": \"босс\", \"found_in_part\": \"боcс\", \"confidence\": 0.8}\n";
        $prompt .= "- Не команда: {\"is_command\": false, \"command_id\": null, \"found_keyword\": null, \"found_in_part\": null, \"confidence\": null}";

        return $prompt;
    }

    public function handle(MessagesModel $message): array
    {
        $text = trim($message->text);

        $this->sendDebugMessage($message, "Обработка командного фильтра", [
            'text' => $text
        ]);

        Log::info('Обработка командного фильтра', [
            'message_id' => $message->id,
            'text' => $text,
            'filter_id' => $this->getFilterId()
        ]);

        $exactMatch = $this->match($text);

        if ($exactMatch !== false) {
            $this->sendDebugMessage($message, "Найдено точное совпадение команды", [
                'matched_ids' => $exactMatch
            ]);

            $this->processCommand($message, $exactMatch[0]);

            return $this->createResponse(false, self::DECISION_SKIP, self::STATUS_COMPLETED, [
                'reason' => 'exact_command_match',
                'matched_ids' => $exactMatch,
                'decision' => 'skip_processing'
            ]);
        }

        return $this->handleWithAiCheck($message, $text);
    }

    protected function handleWithAiCheck(MessagesModel $message, string $text): array
    {
        $aiRequestId = $this->createAiRequest($message, $text);

        if ($aiRequestId) {
            $this->sendDebugMessage($message, "AI запрос создан и отправлен в обработку", [
                'ai_request_id' => $aiRequestId
            ]);

            ProcessLlmRequest::dispatch($aiRequestId);

            return [
                'status' => self::STATUS_PENDING,
                'ai_request_id' => $aiRequestId,
                'decision' => self::DECISION_WAIT_EXTERNAL,
                'filter_id' => $this->getFilterId(),
                'reason' => 'ai_command_check'
            ];
        }

        return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED, [
            'reason' => 'ai_request_failed'
        ]);
    }

    protected function extractAiJson(array $response): ?array
    {
        $responseData = $response['response_data'] ?? $response;
        $textResponse = $responseData['text'] ?? $responseData['response'] ?? $responseData['data'] ?? null;

        if ($textResponse) {
            $clean = preg_replace('/^```[a-zA-Z]*\s*|\s*```$/m', '', $textResponse);

            if (preg_match('/\{.*\}/s', $clean, $matches)) {
                $data = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $data;
                }
            }
        }

        return is_array($responseData) ? $responseData : null;
    }

    protected function parseAiResponse(array $response): bool
    {
        $data = $this->extractAiJson($response);
        return (bool)($data['is_command'] ?? false);
    }

    protected function parseCommandIdFromAiResponse(array $response): ?int
    {
        $data = $this->extractAiJson($response);
        return isset($data['command_id']) ? (int)$data['command_id'] : null;
    }

    protected function processCommand(MessagesModel $message, int $commandId): void
    {
        $processor = new CommandProcessor();
        $processor->executeCommand($commandId, $message);

        $message->update([
            'status' => 1,
            'info->processed_as_command' => true,
            'info->command_id' => $commandId,
            'info->processed_at' => now()->toISOString()
        ]);

        Log::info('Команда обработана и сообщение помечено как обработанное', [
            'message_id' => $message->id,
            'command_id' => $commandId
        ]);
    }

    public static function processAiResponse(int $aiRequestId, array $response): void
    {
        $aiRequest = AiRequest::find($aiRequestId);
        if (!$aiRequest) {
            Log::error("AI request not found", ['ai_request_id' => $aiRequestId]);
            return;
        }

        $messageId = $aiRequest->metadata['message_id'] ?? null;
        $message = MessagesModel::find($messageId);
        if (!$message) {
            Log::error("Message not found for AI request", ['ai_request_id' => $aiRequestId]);
            $aiRequest->update(['status' => 'completed']);
            return;
        }

        $instance = new self();

        $isCommand = $instance->parseAiResponse($response);
        $commandId = $instance->parseCommandIdFromAiResponse($response);

        if ($isCommand && $commandId) {
            $instance->processCommand($message, $commandId);
            Log::info('AI определил команду и вызвал процессор', [
                'message_id' => $message->id,
                'command_id' => $commandId
            ]);
        } else {
            Log::info('AI проверка: команда не определена', [
                'message_id' => $message->id,
                'response' => $response
            ]);
        }

        $aiRequest->update(['status' => 'completed']);
    }

    public function processSavedData(MessagesModel $message, array $result): array
    {
        Log::info('Обработка сохранённых данных в фильтре Commands', [
            'message_id' => $message->id,
            'result_keys' => array_keys($result)
        ]);

        // ПРОВЕРЯЕМ: это наши данные?
        if (!$this->isOurData($result)) {
            Log::info('Данные не относятся к фильтру Commands - пропускаем', [
                'message_id' => $message->id
            ]);
            return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED);
        }

        try {
            // Извлекаем данные из AI-ответа
            $data = $this->extractAiJson($result);
            
            if (!$data) {
                Log::warning('Не удалось извлечь JSON из AI-ответа', [
                    'message_id' => $message->id,
                    'result' => $result
                ]);
                return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED);
            }

            // Основная логика обработки команд...
            if (!($data['is_command'] ?? false)) {
                Log::info('AI подтвердил: не команда', [
                    'message_id' => $message->id,
                    'command_data' => $data
                ]);
                
                return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED);
            }

            // Обработка команды...
            $commandId = $data['command_id'] ?? null;
            if ($commandId) {
                $this->processCommand($message, $commandId);
                return $this->createResponse(false, self::DECISION_SKIP, self::STATUS_COMPLETED);
            }

            return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED);

        } catch (\Throwable $e) {
            Log::error('Ошибка в processSavedData Commands', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
            return $this->createResponse(true, self::DECISION_CONTINUE, self::STATUS_COMPLETED);
        }
    }

    /**
     * Проверяет, относятся ли данные к этому фильтру
     */
    protected function isOurData(array $result): bool
    {
        // Наши данные содержат поля команды AI
        return isset($result['is_command']) || 
            isset($result['command_id']) || 
            (isset($result['data']) && strpos($result['data'] ?? '', 'is_command') !== false);
    }
}