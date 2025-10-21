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
        // убрали raw_external_data из fillable
    ];

    protected $table = 'messages';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $casts = [
        'info' => 'json',
        // убрали raw_external_data из casts
    ];

    protected function asJson($value, $flag = 0)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /**
     * СТРУКТУРА ДАННЫХ ДЛЯ КАЖДОГО ФИЛЬТРА В info->filters[filter_id]:
     * 
     * [
     *     'id' => 2,                              // ID фильтра внутри объекта
     *     'order' => 1,                           // Порядок выполнения
     *     'status' => 'completed',                // Статус выполнения
     *     'processed_at' => '2023-10-01 12:00:00', // Время обработки
     *     'updated_at' => '2023-10-01 12:00:00',   // Время последнего обновления
     *     'external_id' => 'abc123',              // ID внешней обработки
     *     'external_data_processed' => true,      // Флаг обработки внешних данных
     *     'external_requests_count' => 2,         // Количество внешних запросов
     *     'result' => [                           // Результат работы фильтра
     *         'approved' => true,
     *         'decision' => 'continue_processing',
     *         'status' => 'completed',
     *         'filter_id' => 2,
     *         'filter_name' => 'Проверка на команды',
     *         'processed_at' => '2023-10-01T12:00:00.000Z',
     *         'reason' => 'ai_command_check_completed'
     *     ],
     *     'errors' => [                           // Массив ошибок для этого фильтра
     *         [
     *             'code' => 'tts_server_unavailable',
     *             'message' => 'Нет доступного TTS сервера',
     *             'timestamp' => '2025-10-08T08:44:26.236735Z',
     *             'context' => ['filter_id' => 5, 'attempt' => 1]
     *         ]
     *     ],
     *     'raw_datas' => [                        // Массив сырых данных
     *         [
     *             'data' => [...],
     *             'request_type' => 'voice_processing',
     *             'received_at' => '2023-10-01 12:00:00'
     *         ]
     *     ]
     * ]
     */

    /**
     * Сохраняет raw_external_data в info
     */
    public function setRawExternalData(array $data): void
    {
        $info = $this->info ?? [];
        $info['raw_external_data'] = $data;
        $this->info = $info;
    }

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
     * Получает raw_external_data из info
     */
    public function getRawExternalData(): array
    {
        return $this->info['raw_external_data'] ?? [];
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
     * Добавляет raw_data к результату фильтра (для нескольких запросов)
     */
    public function addFilterRawData(int $filterId, array $rawData, string $requestType = null): void
    {
        $info = $this->info ?? [];
        
        if (!isset($info['filters'][$filterId])) {
            $info['filters'][$filterId] = [
                'result' => [],
                'raw_datas' => []  // Множественное число для нескольких запросов
            ];
        }
        
        // Создаем запись raw_data с временной меткой
        $rawDataEntry = [
            'data' => $rawData,
            'received_at' => now()->toDateTimeString()
        ];
        
        // Если указан тип запроса, добавляем его
        if ($requestType) {
            $rawDataEntry['request_type'] = $requestType;
        }
        
        // Добавляем raw_data в массив raw_datas
        if (!isset($info['filters'][$filterId]['raw_datas'])) {
            $info['filters'][$filterId]['raw_datas'] = [];
        }
        
        $info['filters'][$filterId]['raw_datas'][] = $rawDataEntry;
        $info['filters'][$filterId]['updated_at'] = now()->toDateTimeString();
        
        $this->info = $info;
    }

    /**
     * Получает все raw_datas для фильтра
     */
    public function getFilterRawDatas(int $filterId): array
    {
        return $this->info['filters'][$filterId]['raw_datas'] ?? [];
    }

    /**
     * Получает последний raw_data для фильтра
     */
    public function getLastFilterRawData(int $filterId): ?array
    {
        $rawDatas = $this->getFilterRawDatas($filterId);
        return !empty($rawDatas) ? end($rawDatas) : null;
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
}