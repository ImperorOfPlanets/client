<?php

namespace App\Helpers\Assistant\Commands;

use App\Models\Assistant\MessagesModel;
use App\Models\Assistant\MessagesModel as Message;
use App\Jobs\AI\ProcessLlmRequest;
use App\Models\Ai\AiRequest;
use App\Helpers\Ai\AiServiceLocator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Helpers\Socials\SocialInterface;
use App\Models\Socials\SocialsModel;

class PodcastSummary
{
    public function run(MessagesModel $message): array
    {
        try {
            Log::info('🎙️ Команда подкаста запущена', [
                'message_id' => $message->id,
                'chat_id' => $message->chat_id,
                'social_id' => $message->soc,
                'user_info' => $message->info['name'] ?? 'Unknown'
            ]);

            // Отправляем сообщение о начале обработки
            $this->sendProcessingMessage($message);

            // Собираем сообщения для этой соцсети
            $isGroup = $message->info['is_group'] ?? false;
            $recentMessages = $this->getRecentMessages($message->chat_id, $message->soc, $isGroup);
            
            if (empty($recentMessages)) {
                $this->sendNoMessagesResponse($message);
                return ['success' => false, 'reason' => 'no_messages'];
            }

            // Создаем AI запрос для генерации подкаста
            $aiRequestId = $this->createPodcastRequest($message, $recentMessages, $isGroup);
            
            if ($aiRequestId) {
                ProcessLlmRequest::dispatch($aiRequestId);
                
                return [
                    'success' => true,
                    'ai_request_id' => $aiRequestId,
                    'messages_count' => count($recentMessages),
                    'is_group' => $isGroup
                ];
            }

            return ['success' => false, 'reason' => 'ai_request_failed'];

        } catch (\Throwable $e) {
            Log::error('❌ Ошибка в команде подкаста', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->sendErrorMessage($message);
            return ['success' => false, 'reason' => 'exception'];
        }
    }

    /**
     * Сбор сообщений для подкаста (последние 100 сообщений по ID)
     */
    protected function getRecentMessages(string $chatId, int $socialId, bool $isGroup = false): array
    {
        Log::debug('🔍 Сбор сообщений для подкаста - НАЧАЛО', [
            'chat_id' => $chatId,
            'social_id' => $socialId,
            'is_group' => $isGroup
        ]);

        $query = Message::where('soc', $socialId)
            ->where('chat_id', $chatId)
            ->whereNotNull('text')
            ->where('text', '!=', '')
            ->orderBy('id', 'desc')
            ->limit(100);

        Log::debug('🔍 SQL запрос для сбора сообщений', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]);

        $messages = $query->get(['id', 'text', 'info', 'chat_id', 'soc'])
            ->toArray();

        // ДЕТАЛЬНОЕ ЛОГИРОВАНИЕ РЕЗУЛЬТАТОВ
        Log::debug('🔍 РЕЗУЛЬТАТЫ ЗАПРОСА', [
            'total_messages_found' => count($messages),
            'chat_id_in_query' => $chatId,
            'social_id_in_query' => $socialId,
            'first_3_messages' => array_map(function($msg) {
                return [
                    'id' => $msg['id'],
                    'text_preview' => substr($msg['text'] ?? '', 0, 50),
                    'user_name' => $msg['info']['name'] ?? 'Unknown',
                    'user_username' => $msg['info']['username'] ?? 'Unknown',
                ];
            }, array_slice($messages, 0, 3))
        ]);

        // Переворачиваем массив, чтобы сообщения шли в порядке возрастания ID (от старых к новым)
        $messages = array_reverse($messages);

        // Логируем ДЕТАЛЬНУЮ информацию о найденных сообщениях
        if (!empty($messages)) {
            $userStats = [];
            foreach ($messages as $msg) {
                $username = $msg['info']['username'] ?? $msg['info']['name'] ?? 'Unknown';
                $userStats[$username] = ($userStats[$username] ?? 0) + 1;
            }
            
            Log::info('📊 СТАТИСТИКА найденных сообщений', [
                'total_messages' => count($messages),
                'unique_users' => count($userStats),
                'user_message_counts' => $userStats,
                'id_range' => [
                    'oldest_id' => $messages[0]['id'] ?? 'unknown',
                    'newest_id' => $messages[count($messages)-1]['id'] ?? 'unknown'
                ]
            ]);

            // Показываем примеры сообщений от разных пользователей
            $sampleByUser = [];
            foreach ($messages as $msg) {
                $username = $msg['info']['username'] ?? $msg['info']['name'] ?? 'Unknown';
                if (!isset($sampleByUser[$username]) && count($sampleByUser) < 5) {
                    $sampleByUser[$username] = [
                        'user' => $username,
                        'message_preview' => substr($msg['text'], 0, 100),
                        'message_id' => $msg['id']
                    ];
                }
            }

            Log::debug('👥 Примеры сообщений по пользователям', [
                'sample_messages_by_user' => array_values($sampleByUser)
            ]);
        }

        Log::info('📨 Собраны сообщения для подкаста', [
            'chat_id' => $chatId,
            'social_id' => $socialId,
            'is_group' => $isGroup,
            'messages_count' => count($messages),
            'limit_applied' => '100 messages (by ID desc)',
            'query_conditions' => [
                'soc' => $socialId,
                'chat_id' => $chatId,
                'text_not_empty' => true
            ]
        ]);

        return $messages;
    }

    /**
     * Создание AI запроса для генерации подкаста - ПО АНАЛОГИИ С GREETINGGENERATOR
     */
    protected function createPodcastRequest(MessagesModel $message, array $recentMessages, bool $isGroup): ?int
    {
        $services = AiServiceLocator::getAllActiveServices();
        
        if (empty($services)) {
            Log::error('Нет активных AI сервисов для генерации подкаста');
            return null;
        }

        $prompt = $this->generatePodcastPrompt($recentMessages, $message->info, $isGroup);

        try {
            // СТРУКТУРА КАК В GREETINGGENERATOR
            $requestData = [
                'prompt' => $prompt,
                'original_message' => $message->text,
                'user_info' => $message->info,
                'response_format' => 'text',
                'temperature' => 0.8,
                'max_tokens' => 1500,
                'messages_count' => count($recentMessages),
                'is_group_chat' => $isGroup
            ];

            $aiRequest = AiRequest::create([
                'service_id' => $services[0]->id,
                'request_data' => $requestData,
                'metadata' => [
                    'message_id' => $message->id,
                    'command_type' => 'podcast_summary',
                    'chat_id' => $message->chat_id,
                    'social_id' => $message->soc,
                    'user_id' => $message->info['from'] ?? null,
                    'user_name' => $message->info['name'] ?? 'Пользователь',
                    'is_group' => $isGroup,
                    'message_info' => $message->info,
                    'messages_processed' => count($recentMessages),
                    'processing_callback' => [
                        'type' => 'command_completion',
                        'command_class' => self::class,
                        'method' => 'processPodcastResponse'
                    ]
                ],
                'status' => 'pending'
            ]);

            Log::info('🎯 Создан AI запрос для генерации подкаста', [
                'ai_request_id' => $aiRequest->id,
                'message_id' => $message->id,
                'messages_count' => count($recentMessages),
                'is_group' => $isGroup,
                'prompt_length' => strlen($prompt),
                'service_used' => $services[0]->name ?? 'Unknown'
            ]);

            return $aiRequest->id;

        } catch (\Throwable $e) {
            Log::error("Ошибка создания AI запроса для подкаста: " . $e->getMessage(), [
                'message_id' => $message->id,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Генерация промпта для подкаста с учетом типа чата
     */
    protected function generatePodcastPrompt(array $messages, array $chatInfo, bool $isGroup): string
    {
        $messagesText = $this->formatMessagesForPrompt($messages);
        $chatType = $isGroup ? "группового чата" : "личной беседы";
        $userName = $chatInfo['name'] ?? 'Пользователь';
        
        $totalMessages = count($messages);

        // Собираем статистику по пользователям для промпта
        $userStats = [];
        foreach ($messages as $msg) {
            $username = $msg['info']['username'] ?? $msg['info']['name'] ?? 'Unknown';
            $userStats[$username] = ($userStats[$username] ?? 0) + 1;
        }

        // ОПИСАНИЕ АССИСТЕНТА КАК В GREETINGGENERATOR
        $assistantDescription = "Тебя зовут Ассистия ты помощник. Император Планет твой создатель. Вот твое краткое описание. Старшая сестра — Ассистия. Ты — дух взрослого чата 18+. Ассистия — это безупречная секретарша с аналитическим умом суперкомпьютера. Она собранна, эффективна и всегда на шаг впереди. Её стихия — порядок, структура и результативность. Она не ведёт беседу, а направляет её к ясным и практичным выводам.";

        $prompt = "{$assistantDescription}\n\n";
        $prompt .= "Ты - ведущий весёлого ироничного подкаста. Создай краткую юмористическую сводку последних сообщений из {$chatType}.\n\n";
        
        if ($isGroup) {
            $prompt .= "Это групповой чат, показаны последние {$totalMessages} сообщений от " . count($userStats) . " участников.\n\n";
        } else {
            $prompt .= "Это личная беседа с пользователем {$userName}, показаны последние {$totalMessages} сообщений.\n\n";
        }

        // Добавляем статистику по пользователям в промпт
        if (count($userStats) > 0) {
            $prompt .= "СТАТИСТИКА УЧАСТНИКОВ:\n";
            foreach ($userStats as $username => $count) {
                $prompt .= "- {$username}: {$count} сообщений\n";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "ИСТОРИЯ СООБЩЕНИЙ (в хронологическом порядке):\n";
        $prompt .= "{$messagesText}\n\n";
        
        $prompt .= "ТРЕБОВАНИЯ К СВОДКЕ:\n";
        $prompt .= "1. Длина: 3-5 абзацев\n";
        $prompt .= "2. Тон: ироничный, дружеский, с юмором\n";
        $prompt .= "3. Учитывай контекст: {$chatType}\n";
        $prompt .= "4. Структура:\n";
        $prompt .= "   - Начало: завлекающее вступление\n";
        $prompt .= "   - Основная часть: краткий обзор ключевых тем\n";
        $prompt .= "   - Завершение: смешной вывод или пожелание\n";
        $prompt .= "5. Используй:\n";
        $prompt .= "   - Шутки и иронию\n";
        $prompt .= "   - Разговорный стиль\n";
        $prompt .= "   - Эмоции и экспрессию\n";
        $prompt .= "6. Избегай:\n";
        $prompt .= "   - Оскорблений и грубостей\n";
        $prompt .= "   - Слишком длинных предложений\n";
        $prompt .= "   - Формального тона\n\n";
        
        $prompt .= "Пример стиля для группы:\n";
        $prompt .= "\"Всем привет в нашем ежедневном подкасте! В нашем чате творилось вот что... \n";
        $prompt .= "Кто-то пытался решить мировые проблемы, а кто-то просто делился мемами. \n";
        $prompt .= "В общем, как обычно - смех и грех!\"\n\n";
        
        $prompt .= "Пример стиля для личной беседы:\n";
        $prompt .= "\"Приветствую в нашем личном подкасте! У нас интересная беседа... \n";
        $prompt .= "Обсуждали важные темы и делились мыслями. \n";
        $prompt .= "Было очень душевно!\"\n\n";
        
        $prompt .= "ПОДКАСТ-СВОДКА:";

        return $prompt;
    }

    /**
     * Форматирование сообщений для промпта
     */
    protected function formatMessagesForPrompt(array $messages): string
    {
        $formatted = [];
        
        foreach ($messages as $index => $message) {
            try {
                // БЕЗОПАСНОЕ получение информации о пользователе
                $userName = $message['info']['name'] ?? 'Аноним';
                $userUsername = $message['info']['username'] ?? null;
                
                // Используем username если есть, иначе имя
                $userDisplay = $userUsername ? "@{$userUsername}" : $userName;
                
                $text = substr($message['text'], 0, 200); // Обрезаем длинные сообщения
                
                $formatted[] = "{$userDisplay}: {$text}";
                
            } catch (\Throwable $e) {
                Log::warning('Ошибка форматирования сообщения для промпта', [
                    'message_id' => $message['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
                
                // Fallback - просто текст без информации о пользователе
                $text = substr($message['text'], 0, 200);
                $formatted[] = "Неизвестный пользователь: {$text}";
            }
        }

        Log::debug('📋 Отформатировано сообщений для промпта', [
            'total_formatted' => count($formatted),
            'first_3_formatted' => array_slice($formatted, 0, 3)
        ]);

        return implode("\n", $formatted);
    }

    /**
     * Обработка ответа от AI - ПО АНАЛОГИИ С GREETINGGENERATOR
     */
    public static function processPodcastResponse(int $aiRequestId, array $response): void
    {
        $aiRequest = AiRequest::find($aiRequestId);
        if (!$aiRequest) {
            Log::error("AI request not found for podcast", ['ai_request_id' => $aiRequestId]);
            return;
        }

        $messageId = $aiRequest->metadata['message_id'] ?? null;
        $message = MessagesModel::find($messageId);
        if (!$message) {
            Log::error("Message not found for podcast AI request", ['ai_request_id' => $aiRequestId]);
            $aiRequest->update(['status' => 'completed']);
            return;
        }

        $instance = new self();
        $podcastText = $instance->extractPodcastFromResponse($response);
        
        if ($podcastText && strlen($podcastText) > 100) {
            $instance->sendPodcastToChat($message, $podcastText);
            
            Log::info('🎙️ Подкаст сгенерирован и отправлен', [
                'message_id' => $message->id,
                'podcast_length' => strlen($podcastText),
                'ai_request_id' => $aiRequestId,
                'is_group' => $aiRequest->metadata['is_group'] ?? false,
                'messages_processed' => $aiRequest->metadata['messages_processed'] ?? 0
            ]);
        } else {
            Log::warning('Не удалось извлечь подкаст из AI response', [
                'message_id' => $message->id,
                'response_data_keys' => array_keys($response),
                'extracted_text_length' => strlen($podcastText)
            ]);
            
            $instance->sendPodcastError($message);
        }

        $aiRequest->update(['status' => 'completed']);

        // Помечаем, что подкаст уже сгенерирован
        $message->update([
            'info->podcast_generated' => true,
            'info->podcast_text' => $podcastText,
            'info->podcast_generated_at' => now()->toISOString()
        ]);
    }

    /**
     * Извлечение текста подкаста из ответа AI - ПО АНАЛОГИИ С GREETINGGENERATOR
     */
    protected function extractPodcastFromResponse(array $response): string
    {
        Log::debug('Извлечение подкаста из ответа AI', [
            'response_structure' => array_keys($response),
            'has_data_key' => isset($response['data']),
            'data_type' => isset($response['data']) ? gettype($response['data']) : 'not_set'
        ]);

        $text = '';

        // Обрабатываем разные форматы ответов КАК В GREETINGGENERATOR
        if (isset($response['data']) && is_string($response['data'])) {
            // Формат: data как строка
            $text = $response['data'];
        } 
        elseif (isset($response['data']) && is_array($response['data'])) {
            $data = $response['data'];
            
            // Формат PolzaAI: data.data
            if (isset($data['data']) && is_string($data['data'])) {
                $text = $data['data'];
            }
            // Другие форматы
            else {
                $text = 
                    $data['text'] ??
                    $data['response'] ??
                    $data['choices'][0]['message']['content'] ??
                    $data['choices'][0]['text'] ??
                    '';
            }
        }
        // Прямой доступ к полям ответа
        else {
            $text =
                $response['text'] ??
                $response['response'] ??
                $response['choices'][0]['message']['content'] ??
                $response['choices'][0]['text'] ??
                '';
        }

        if (!is_string($text)) {
            Log::warning('Извлеченный текст не является строкой', [
                'text_type' => gettype($text),
                'text_value' => $text
            ]);
            $text = '';
        }

        // Очистка текста КАК В GREETINGGENERATOR
        $cleanText = preg_replace('/^```\w*\s*|\s*```$/m', '', $text);
        $cleanText = preg_replace('/^(Ответ|Подкаст|ПОДКАСТ|Сводка|СВОДКА)[:\s]*/i', '', $cleanText);
        $cleanText = trim($cleanText);

        Log::debug('Результат извлечения подкаста', [
            'original_length' => strlen($text),
            'clean_length' => strlen($cleanText),
            'clean_text_preview' => substr($cleanText, 0, 100) . (strlen($cleanText) > 100 ? '...' : '')
        ]);

        return $cleanText;
    }

    /**
     * Отправка сообщения о начале обработки
     */
    protected function sendProcessingMessage(MessagesModel $message): void
    {
        $isGroup = $message->info['is_group'] ?? false;
        $chatType = $isGroup ? "группового чата" : "личной беседы";
        
        $text = "🎙️ Запускаю генерацию подкаста для {$chatType}...\n";
        $text .= "Собираю последние сообщения и готовлю юмористическую сводку!";

        $this->sendMessageDirectly($message, $text);
    }

    /**
     * Отправка подкаста в чат
     */
    protected function sendPodcastToChat(MessagesModel $message, string $podcastText): void
    {
        $isGroup = $message->info['is_group'] ?? false;
        $chatType = $isGroup ? "ГРУППОВОГО ЧАТА" : "ЛИЧНОЙ БЕСЕДЫ";
        
        $formattedText = "🎙️ *ПОДКАСТ {$chatType}*\n\n";
        $formattedText .= "{$podcastText}\n\n";
        $formattedText .= "---\n";
        $formattedText .= "🤖 *Сводка по последним сообщениям*";

        $this->sendMessageDirectly($message, $formattedText);
    }

    /**
     * Отправка сообщения об отсутствии сообщений
     */
    protected function sendNoMessagesResponse(MessagesModel $message): void
    {
        $isGroup = $message->info['is_group'] ?? false;
        $chatType = $isGroup ? "групповом чате" : "личной беседе";
        
        $text = "🤷‍♂️ В {$chatType} не найдено сообщений для подкаста.\n";
        $text .= "Поактивничайте немного, а потом попробуйте снова!";

        $this->sendMessageDirectly($message, $text);
    }

    /**
     * Отправка сообщения об ошибке генерации подкаста
     */
    protected function sendPodcastError(MessagesModel $message): void
    {
        $text = "😅 Не удалось сгенерировать подкаст. Попробуйте позже!";

        $this->sendMessageDirectly($message, $text);
    }

    /**
     * Отправка сообщения об общей ошибке
     */
    protected function sendErrorMessage(MessagesModel $message): void
    {
        $text = "❌ Произошла ошибка при создании подкаста. Попробуйте еще раз.";

        $this->sendMessageDirectly($message, $text);
    }

    /**
     * Прямая отправка сообщения через социальную сеть
     */
    protected function sendMessageDirectly(MessagesModel $message, string $text, array $params = []): void
    {
        try {
            $social = $this->getSocialInstance($message->soc);
            
            if (!$social) {
                Log::error('Social instance not found for message', [
                    'message_id' => $message->id,
                    'social_id' => $message->soc
                ]);
                return;
            }

            // Добавляем reply_for если есть
            if (isset($message->info['message_id'])) {
                $params['reply_for'] = $message->info['message_id'];
            }

            $result = $social->sendMessage($message->chat_id, $text, $params);
            $social->processResultSendMessage($result);

            Log::info('Сообщение отправлено через PodcastSummary', [
                'message_id' => $message->id,
                'chat_id' => $message->chat_id,
                'social_id' => $message->soc,
                'text_length' => strlen($text)
            ]);

        } catch (\Throwable $e) {
            Log::error('Ошибка отправки сообщения в PodcastSummary', [
                'message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Получение экземпляра социальной сети
     */
    protected function getSocialInstance(int $socialId): ?SocialInterface
    {
        try {
            $social = SocialsModel::find($socialId);
            if (!$social) {
                Log::error('Social network not found', ['social_id' => $socialId]);
                return null;
            }

            $className = $social->propertyById(35)?->pivot->value;
            if (!$className || !class_exists($className)) {
                Log::error('Social network class not found or invalid', [
                    'social_id' => $socialId,
                    'class_path' => $className
                ]);
                return null;
            }

            $instance = new $className();
            return $instance instanceof SocialInterface ? $instance : null;

        } catch (\Throwable $e) {
            Log::error('Error getting social instance', [
                'social_id' => $socialId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}