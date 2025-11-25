<?php
namespace App\Models\Assistant;

use Illuminate\Database\Eloquent\Model;

class MessagesModel extends Model
{
    protected $fillable = [
        'text',
        'soc',
        'chat_id',
        'info'
    ];

    protected $table = 'messages';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $casts = [
        'info' => 'json',
    ];

    protected function asJson($value, $flag = 0)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /**
     * СТРУКТУРА ПОЛЯ info:
     * 
     * 'info' => [
     *     // === ОСНОВНЫЕ ДАННЫЕ СООБЩЕНИЯ ===
     *     'message_type' => 'text|audio|image|video|file|mixed|unknown', 
     *     // mixed - когда есть несколько типов контента (например, текст + картинки)
     *     
     *     'message_id' => 12345,              // ID сообщения в социальной сети
     *     'from' => 123456789,                // ID отправителя
     *     'name' => 'Иван Иванов',            // Имя отправителя
     *     'username' => 'ivanov',             // Username отправителя (если есть)
     *     'chat_type' => 'private',           // Тип чата: private, group, supergroup, channel
     *     'date' => 1633046400,               // Timestamp создания сообщения
     *     
     *     // === ССЫЛКИ И КОНТЕКСТ ===
     *     'reply_to' => 12344,                // ID сообщения, на которое отвечаем
     *     'thread_id' => 67890,               // ID треда/темы (для Telegram topics)
     *     
     *     // === МАССИВ ФАЙЛОВ И МЕДИА ===
     *     'attachments' => [
     *         [
     *             'type' => 'audio',           // Тип вложения: audio, image, video, document, voice, sticker
     *             'file_id' => 'AwADBAADbXXXXXXXXXXX', // ID файла в социальной сети
     *             'file_unique_id' => 'unique123',     // Уникальный ID файла
     *             'duration' => 120,                   // Длительность в секундах (для audio/voice/video)
     *             'file_size' => 1024000,              // Размер файла в байтах
     *             'mime_type' => 'audio/ogg',         // MIME-тип файла
     *             'width' => 1920,                    // Ширина (для image/video)
     *             'height' => 1080,                   // Высота (для image/video)
     *             'file_name' => 'document.pdf',      // Имя файла (для document)
     *             'link_mp3' => 'https://...',        // Прямая ссылка на файл (VK)
     *             'link_ogg' => 'https://...',        // Альтернативная ссылка (VK)
     *             'thumbnail' => [                    // Превью (если есть)
     *                 'file_id' => '...',
     *                 'file_unique_id' => '...',
     *                 'file_size' => 50000,
     *                 'width' => 320,
     *                 'height' => 240
     *             ]
     *         ]
     *     ],
     *     
     *     // === СЛУЖЕБНЫЕ ФЛАГИ ===
     *     'processing_message_id' => 123,     // ID служебного сообщения "обработка"
     *     'processed_as_command' => false,    // Флаг: сообщение обработано как команда
     *     'is_debug' => false,                // Флаг: отладочное сообщение
     *     'is_bot_response' => false,         // Флаг: сообщение от бота
     *     
     *     // === СИСТЕМА ФИЛЬТРОВ ===
     *     'filters' => [
     *         2 => [ // ID фильтра
     *             'id' => 2,                          // ID фильтра внутри объекта
     *             'order' => 1,                       // Порядок выполнения
     *             'status' => 'completed',            // Статус выполнения: pending, processing, completed, failed
     *             'processed_at' => '2023-10-01 12:00:00', // Время обработки
     *             'updated_at' => '2023-10-01 12:00:00',   // Время последнего обновления
     *             'external_id' => 'abc123',          // ID внешней обработки
     *             'external_data_processed' => true,  // Флаг обработки внешних данных
     *             'external_requests_count' => 2,     // Количество внешних запросов
     *             
     *             // ⚡ ИСХОДЯЩИЕ ДАННЫЕ (от нас к внешним сервисам)
     *             'outgoing_data' => [                // Что мы отправляем на обработку
     *                 'request_payload' => [...],     // Данные запроса
     *                 'voice_file_url' => 'https://...', // Файлы для обработки
     *                 'parameters' => [...],          // Параметры запроса
     *                 'metadata' => [...]             // Дополнительные метаданные
     *             ],
     *             
     *             // ⚡ ВХОДЯЩИЕ ДАННЫЕ (от внешних сервисов к нам)  
     *             'incoming_data' => [                // Что мы получаем в ответ
     *                 [
     *                     'source' => 'voice_recognition_service',
     *                     'data' => [...],            // Данные ответа
     *                     'received_at' => '2023-10-01 12:00:00'
     *                 ],
     *                 [
     *                     'source' => 'sentiment_analysis',
     *                     'data' => [...],
     *                     'received_at' => '2023-10-01 12:00:01'
     *                 ]
     *             ],
     *             
     *             'result' => [                       // Финальный результат работы фильтра
     *                 'approved' => true,
     *                 'decision' => 'continue_processing',
     *                 'status' => 'completed',
     *                 'filter_id' => 2,
     *                 'filter_name' => 'Проверка на команды',
     *                 'processed_at' => '2023-10-01T12:00:00.000Z',
     *                 'reason' => 'ai_command_check_completed'
     *             ],
     *             'errors' => [                       // Ошибки фильтра
     *                 [
     *                     'code' => 'tts_server_unavailable',
     *                     'message' => 'Нет доступного TTS сервера',
     *                     'timestamp' => '2025-10-08T08:44:26.236735Z',
     *                     'context' => ['filter_id' => 5, 'attempt' => 1]
     *                 ]
     *             ]
     *         ]
     *     ]
     * ]
     */

    // ==================== МЕТОДЫ ДЛЯ РАБОТЫ С ФИЛЬТРАМИ ====================

    /**
     * Добавляет ошибку к конкретному фильтру
     */
    public function addFilterError(int $filterId, string $code, string $message, array $context = []): void
    {
        $info = $this->info ?? [];
        
        if (!isset($info['filters'][$filterId])) {
            $info['filters'][$filterId] = [];
        }
        
        if (!isset($info['filters'][$filterId]['errors'])) {
            $info['filters'][$filterId]['errors'] = [];
        }
        
        $errorEntry = [
            'code' => $code,
            'message' => $message,
            'timestamp' => now()->toISOString(),
            'context' => $context
        ];
        
        $info['filters'][$filterId]['errors'][] = $errorEntry;
        $info['filters'][$filterId]['updated_at'] = now()->toDateTimeString();
        
        $this->info = $info;
    }

    /**
     * Получает ошибки для конкретного фильтра
     */
    public function getFilterErrors(int $filterId): array
    {
        return $this->info['filters'][$filterId]['errors'] ?? [];
    }

    /**
     * Обновляет базовую информацию фильтра (ID, порядок)
     */
    public function updateFilterBaseInfo(int $filterId, array $filterConfig): void
    {
        $info = $this->info ?? [];
        
        if (!isset($info['filters'][$filterId])) {
            $info['filters'][$filterId] = [];
        }
        
        $info['filters'][$filterId]['id'] = $filterId;
        $info['filters'][$filterId]['order'] = $filterConfig['order'] ?? 0;
        $info['filters'][$filterId]['name'] = $filterConfig['name'] ?? 'Неизвестный фильтр';
        $info['filters'][$filterId]['updated_at'] = now()->toDateTimeString();
        
        $this->info = $info;
    }

    /**
     * Сохраняет outgoing_data для конкретного фильтра (что отправляем на обработку)
     */
    public function setFilterOutgoingData(int $filterId, array $data): void
    {
        $info = $this->info ?? [];
        
        if (!isset($info['filters'][$filterId])) {
            $info['filters'][$filterId] = [];
        }
        
        $info['filters'][$filterId]['outgoing_data'] = $data;
        $info['filters'][$filterId]['updated_at'] = now()->toDateTimeString();
        
        $this->info = $info;
    }

    /**
     * Получает outgoing_data для конкретного фильтра
     */
    public function getFilterOutgoingData(int $filterId): array
    {
        return $this->info['filters'][$filterId]['outgoing_data'] ?? [];
    }

    /**
     * Добавляет incoming_data к фильтру (что получаем в ответ)
     */
    public function addIncomingData(int $filterId, string $source, array $data): void
    {
        $info = $this->info ?? [];
        
        if (!isset($info['filters'][$filterId])) {
            $info['filters'][$filterId] = [];
        }
        
        if (!isset($info['filters'][$filterId]['incoming_data'])) {
            $info['filters'][$filterId]['incoming_data'] = [];
        }
        
        $incomingEntry = [
            'source' => $source,
            'data' => $data,
            'received_at' => now()->toDateTimeString()
        ];
        
        $info['filters'][$filterId]['incoming_data'][] = $incomingEntry;
        $info['filters'][$filterId]['updated_at'] = now()->toDateTimeString();
        
        $this->info = $info;
    }

    /**
     * Получает все incoming_data для фильтра
     */
    public function getIncomingData(int $filterId): array
    {
        return $this->info['filters'][$filterId]['incoming_data'] ?? [];
    }

    /**
     * Получает последние incoming_data для фильтра
     */
    public function getLastIncomingData(int $filterId): ?array
    {
        $incoming = $this->getIncomingData($filterId);
        return !empty($incoming) ? end($incoming) : null;
    }

    /**
     * Добавляет результат фильтра в info->filters
     */
    public function addFilterResult(int $filterId, array $result): void
    {
        $info = $this->info ?? [];
        $info['filters'][$filterId] = $result;
        $this->info = $info;
    }

    /**
     * Получает все результаты фильтров из info->filters
     */
    public function getAllFilters(): array
    {
        return $this->info['filters'] ?? [];
    }

    /**
     * Проверяет, завершен ли фильтр
     */
    public function isFilterCompleted(int $filterId): bool
    {
        return isset($this->info['filters'][$filterId]);
    }

    /**
     * Получает результат конкретного фильтра
     */
    public function getFilterResult(int $filterId): ?array
    {
        return $this->info['filters'][$filterId] ?? null;
    }

    /**
     * Обновляет статус фильтра
     */
    public function updateFilterStatus(int $filterId, string $status, array $additionalData = []): void
    {
        $info = $this->info ?? [];
        
        if (!isset($info['filters'][$filterId])) {
            $info['filters'][$filterId] = [];
        }
        
        $info['filters'][$filterId]['status'] = $status;
        $info['filters'][$filterId]['updated_at'] = now()->toDateTimeString();
        $info['filters'][$filterId] = array_merge($info['filters'][$filterId], $additionalData);
        
        $this->info = $info;
    }

    /**
     * Получает фильтры по статусу
     */
    public function getFiltersByStatus(string $status): array
    {
        $filters = $this->getAllFilters();
        $result = [];
        
        foreach ($filters as $filterId => $filterData) {
            if (($filterData['status'] ?? '') === $status) {
                $result[$filterId] = $filterData;
            }
        }
        
        return $result;
    }

    /**
     * Проверяет, есть ли фильтры в статусе pending
     */
    public function hasPendingFilters(): bool
    {
        return !empty($this->getFiltersByStatus('pending'));
    }

    /**
     * Получает ID всех завершенных фильтров
     */
    public function getCompletedFilterIds(): array
    {
        $completedFilters = $this->getFiltersByStatus('completed');
        return array_keys($completedFilters);
    }

    /**
     * Получает ID всех ожидающих фильтров
     */
    public function getPendingFilterIds(): array
    {
        $pendingFilters = $this->getFiltersByStatus('pending');
        return array_keys($pendingFilters);
    }

    /**
     * Получает полную структуру фильтра с outgoing и incoming данными
     */
    public function getFilterWithExchangeData(int $filterId): ?array
    {
        $filter = $this->getFilterResult($filterId);
        if (!$filter) {
            return null;
        }

        return [
            'filter_info' => $filter,
            'outgoing_data' => $this->getFilterOutgoingData($filterId),
            'incoming_data' => $this->getIncomingData($filterId),
            'errors' => $this->getFilterErrors($filterId)
        ];
    }

    // ==================== ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ ====================

    /**
     * Проверяет, есть ли у сообщения вложения
     */
    public function hasAttachments(): bool
    {
        return !empty($this->info['attachments'] ?? []);
    }

    /**
     * Получает все вложения определенного типа
     */
    public function getAttachmentsByType(string $type): array
    {
        $attachments = $this->info['attachments'] ?? [];
        return array_filter($attachments, function($attachment) use ($type) {
            return ($attachment['type'] ?? '') === $type;
        });
    }

    /**
     * Получает первый файл определенного типа
     */
    public function getFirstAttachmentByType(string $type): ?array
    {
        $attachments = $this->getAttachmentsByType($type);
        return !empty($attachments) ? reset($attachments) : null;
    }

    /**
     * Проверяет, является ли сообщение командой
     */
    public function isCommand(): bool
    {
        return $this->info['processed_as_command'] ?? false;
    }

    /**
     * Помечает сообщение как обработанное командой
     */
    public function markAsCommand(): void
    {
        $info = $this->info ?? [];
        $info['processed_as_command'] = true;
        $this->info = $info;
    }

    /**
     * Получает ID сообщения для ответа (reply_to)
     */
    public function getReplyToMessageId(): ?int
    {
        return $this->info['reply_to'] ?? null;
    }

    /**
     * Получает ID треда (для Telegram topics)
     */
    public function getThreadId(): ?int
    {
        return $this->info['thread_id'] ?? null;
    }
}
