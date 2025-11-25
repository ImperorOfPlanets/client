<?php

namespace App\Jobs\Assistant\Messages;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

use App\Models\Assistant\MessagesModel;
use App\Helpers\Assistant\FilterProcessor;
use App\Filters\Filter;
use App\Helpers\Logs\Logs as Logator;

class ProcessingResult implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $params = null;
    public $message = null;
    public $logator;

    public function __construct($params = null)
    {
        $this->params = $params;
    }

    public function handle()
    {
        try {
            $this->message = MessagesModel::find($this->params['message_id']);

            if (!$this->message) {
                Log::error('Сообщение не найдено', ['message_id' => $this->params['message_id']]);
                return;
            }

            $this->logator = new Logator;
            $this->logator->setAuthor('ProcessingResult');
            $this->logator->setType('success');
            $this->logator->setText("Обрабатываю результат сервера");
            $this->logator->write();

            // Получаем результат обработки
            $result = $this->params['result'] ?? [];
            
            // Если result является JSON-строкой, декодируем её
            if (is_string($result)) {
                $result = json_decode($result, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::error('Ошибка декодирования JSON результата', [
                        'message_id' => $this->message->id,
                        'result' => $this->params['result']
                    ]);
                    return;
                }
            }

            Log::info('ProcessingResult получил данные', [
                'message_id' => $this->message->id,
                'result_keys' => array_keys($result)
            ]);

            // ⚡️ ЧИСТЫЙ ПРОЦЕСС: Сохраняем и маршрутизируем
            $this->executeProcessingFlow($result);

        } catch (\Exception $e) {
            Log::error('Ошибка в ProcessingResult', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'params' => $this->params
            ]);
        }
    }

    /**
     * Основной процесс обработки
     */
    protected function executeProcessingFlow(array $result): void
    {
        Log::info('🚀 Запуск процесса обработки внешнего результата', [
            'message_id' => $this->message->id,
            'result_type' => gettype($result),
            'result_keys' => array_keys($result)
        ]);

        // 1. Сохраняем входящие данные
        $this->storeIncomingData($result);

        // 2. Загружаем класс фильтра на основе пройденных фильтров из info->filters
        $filterClass = $this->getFilterClassForSavedData();

        Log::info('Выбран фильтр для обработки', [
            'message_id' => $this->message->id,
            'filter_class' => $filterClass
        ]);

        if (class_exists($filterClass)) {
            $filterInstance = new $filterClass();
            $processResult = $filterInstance->processSavedData($this->message, $result);

            Log::info('Обработка сохраненных данных фильтром завершена', [
                'message_id' => $this->message->id,
                'filter_class' => $filterClass,
                'process_result_keys' => array_keys($processResult)
            ]);

            // 3. Обновляем информацию о фильтре в info->filters
            $this->updateFilterStatus($filterInstance, $processResult);
            
            // 4. Продолжаем цепочку фильтров если нужно
            $this->continueFilterChain($filterInstance, $processResult);
            
        } else {
            Log::error('Класс фильтра для обработки сохраненных данных не найден', [
                'filter_class' => $filterClass,
                'message_id' => $this->message->id
            ]);
        }
        
        Log::info('✅ Процесс обработки завершен', [
            'message_id' => $this->message->id
        ]);
    }

    /**
     * Сохраняет входящие данные в сообщение
     */
    protected function storeIncomingData(array $result): void
    {
        try {
            // Сохраняем в info->raw_external_data вместо отдельной колонки
            $this->message->setRawExternalData($result);
            $this->message->save();
            
            Log::info('Входящие данные сохранены в info->raw_external_data', [
                'message_id' => $this->message->id,
                'data_keys' => array_keys($result)
            ]);
        } catch (\Exception $e) {
            Log::error('Ошибка сохранения входящих данных', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Получает класс фильтра для обработки сохраненных данных
     * на основе уже пройденных фильтров в цепочке из поля info->filters
     */
    protected function getFilterClassForSavedData(): string
    {
        try {
            // Получаем информацию о пройденных фильтрах из info->filters
            $messageFilters = $this->message->getAllFilters();
            
            if (empty($messageFilters)) {
                Log::warning('Нет информации о пройденных фильтрах в info->filters', [
                    'message_id' => $this->message->id
                ]);
                return $this->getDefaultFilter();
            }

            // Получаем активные фильтры
            $activeFilters = FilterProcessor::getActiveFilters();
            
            if (empty($activeFilters)) {
                Log::warning('Нет активных фильтров', [
                    'message_id' => $this->message->id
                ]);
                return $this->getDefaultFilter();
            }

            Log::info('Анализ пройденных фильтров из info->filters', [
                'message_id' => $this->message->id,
                'пройденные_фильтры' => array_keys($messageFilters),
                'активные_фильтры' => array_column($activeFilters, 'id')
            ]);

            // ПРИОРИТЕТ 1: Ищем фильтр, который находится в статусе pending для ЭТОГО сообщения
            $targetFilter = $this->findInterruptedFilter($messageFilters, $activeFilters);
            
            if ($targetFilter) {
                Log::info('Найден прерванный фильтр для продолжения обработки', [
                    'message_id' => $this->message->id,
                    'filter_id' => $targetFilter['id'],
                    'filter_class' => $this->extractFilterClassFromHandler($targetFilter)
                ]);
                return $this->extractFilterClassFromHandler($targetFilter);
            }

            // ПРИОРИТЕТ 2: Ищем следующий фильтр в цепочке
            $nextFilter = $this->findNextFilterInChain($messageFilters, $activeFilters);
            
            if ($nextFilter) {
                Log::info('Найден следующий фильтр в цепочке', [
                    'message_id' => $this->message->id,
                    'filter_id' => $nextFilter['id'],
                    'filter_class' => $this->extractFilterClassFromHandler($nextFilter)
                ]);
                return $this->extractFilterClassFromHandler($nextFilter);
            }

            // ПРИОРИТЕТ 3: Используем последний завершенный фильтр
            $lastFilter = $this->findLastCompletedFilter($messageFilters, $activeFilters);
            if ($lastFilter) {
                Log::info('Используем последний завершенный фильтр', [
                    'message_id' => $this->message->id,
                    'filter_id' => $lastFilter['id'],
                    'filter_class' => $this->extractFilterClassFromHandler($lastFilter)
                ]);
                return $this->extractFilterClassFromHandler($lastFilter);
            }

            Log::warning('Не удалось определить подходящий фильтр', [
                'message_id' => $this->message->id
            ]);
            
            return $this->getDefaultFilter();

        } catch (\Exception $e) {
            Log::error('Ошибка определения класса фильтра для сохраненных данных', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->getDefaultFilter();
        }
    }

    /**
     * Ищет фильтр, который был прерван (ожидает внешний результат)
     */
    protected function findInterruptedFilter(array $messageFilters, array $activeFilters): ?array
    {
        foreach ($messageFilters as $filterId => $filterData) {
            $status = $filterData['status'] ?? '';
            $externalId = $filterData['external_id'] ?? null;
            
            Log::debug('Проверка фильтра на прерывание', [
                'filter_id' => $filterId,
                'status' => $status,
                'external_id' => $externalId,
                'message_id' => $this->message->id
            ]);
            
            // Ищем фильтр в статусе pending с external_id
            if ($status === 'pending') {
                // ВАЖНО: external_id может быть null для AI запросов
                // Но если external_id установлен, проверяем соответствие
                if ($externalId === null || $externalId == $this->message->id) {
                    // Находим конфигурацию этого фильтра
                    foreach ($activeFilters as $activeFilter) {
                        if ($activeFilter['id'] === (int)$filterId) {
                            Log::info('Найден ожидающий внешний результат фильтр', [
                                'filter_id' => $filterId,
                                'external_id' => $externalId,
                                'status' => $status,
                                'message_id' => $this->message->id,
                                'filter_class' => $this->extractFilterClassFromHandler($activeFilter)
                            ]);
                            return $activeFilter;
                        }
                    }
                }
            }
        }
        
        Log::debug('Не найдено прерванных фильтров для сообщения', [
            'message_id' => $this->message->id
        ]);
        
        return null;
    }

    /**
     * Ищет следующий фильтр в цепочке обработки
     */
    protected function findNextFilterInChain(array $messageFilters, array $activeFilters): ?array
    {
        $completedFilterIds = array_keys($messageFilters);
        
        // Сортируем активные фильтры по order
        usort($activeFilters, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        
        // Ищем первый активный фильтр, который еще не выполнен
        foreach ($activeFilters as $filter) {
            if (!in_array($filter['id'], $completedFilterIds)) {
                Log::info('Следующий фильтр в цепочке найден', [
                    'filter_id' => $filter['id'],
                    'order' => $filter['order'] ?? 0
                ]);
                return $filter;
            }
        }
        
        return null;
    }

    /**
     * Ищет последний завершенный фильтр
     */
    protected function findLastCompletedFilter(array $messageFilters, array $activeFilters): ?array
    {
        if (empty($messageFilters)) {
            return null;
        }
        
        // Сортируем активные фильтры по order
        usort($activeFilters, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        
        // Ищем самый последний по order завершенный фильтр
        $lastCompletedFilter = null;
        $maxOrder = -1;
        
        foreach ($activeFilters as $filter) {
            $filterId = $filter['id'];
            if (isset($messageFilters[$filterId])) {
                $status = $messageFilters[$filterId]['status'] ?? '';
                if ($status === 'completed') {
                    $order = $filter['order'] ?? 0;
                    if ($order > $maxOrder) {
                        $maxOrder = $order;
                        $lastCompletedFilter = $filter;
                    }
                }
            }
        }
        
        if ($lastCompletedFilter) {
            Log::info('Последний завершенный фильтр найден', [
                'filter_id' => $lastCompletedFilter['id'],
                'order' => $maxOrder
            ]);
        }
        
        return $lastCompletedFilter;
    }

    /**
     * Извлекает класс фильтра из handler конфигурации
     */
    protected function extractFilterClassFromHandler(array $filter): string
    {
        $handler = $filter['parameters']['handler'] ?? '';
        
        if (strpos($handler, '@') !== false) {
            $className = explode('@', $handler)[0];
            
            // Проверяем существование класса
            if (class_exists($className)) {
                return $className;
            }
        }
        
        // Если не удалось извлечь, возвращаем базовый класс
        Log::warning('Не удалось извлечь класс фильтра из handler', [
            'handler' => $handler,
            'filter_id' => $filter['id'] ?? 'unknown'
        ]);
        
        return $this->getDefaultFilter();
    }

    /**
     * Возвращает класс фильтра по умолчанию
     */
    protected function getDefaultFilter(): string
    {
        return Filter::class;
    }

    /**
     * Обновляет статус фильтра в info->filters после обработки сохраненных данных
     * и добавляет raw_data из внешнего результата
     */
    protected function updateFilterStatus(Filter $filter, array $processResult): void
    {
        try {
            $filterId = $filter->getFilterId();
            
            // ОСНОВНОЕ ИСПРАВЛЕНИЕ: Всегда обновляем статус на completed
            // если процесс завершился успешно
            $finalStatus = $processResult['status'] ?? 'completed';
            
            $filterData = [
                'id' => $filterId,
                'order' => $filter->getFilterConfig()['order'] ?? 0,
                'processed_at' => now()->toDateTimeString(),
                'result' => $processResult,
                'status' => $finalStatus, // Явно устанавливаем статус
                'external_data_processed' => true,
                'updated_at' => now()->toDateTimeString(),
                'external_requests_count' => count($this->message->getFilterRawDatas($filterId))
            ];
            
            // Добавляем raw_data если есть
            if (isset($this->params['result'])) {
                $rawData = $this->params['result'];
                if (is_string($rawData)) {
                    $rawData = json_decode($rawData, true);
                }
                $requestType = $this->determineRequestType($rawData);
                $this->message->addFilterRawData($filterId, $rawData, $requestType);
            }
            
            // ВАЖНО: Используем прямой подход для обновления
            $this->message->addFilterResult($filterId, $filterData);
            $this->message->save();
            
            Log::info('Статус фильтра ОБНОВЛЕН', [
                'message_id' => $this->message->id,
                'filter_id' => $filterId,
                'previous_status' => $this->message->getFilterResult($filterId)['status'] ?? 'unknown',
                'new_status' => $finalStatus
            ]);
            
        } catch (\Exception $e) {
            Log::error('Критическая ошибка обновления статуса фильтра', [
                'message_id' => $this->message->id,
                'filter_id' => $filter->getFilterId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Определяет тип запроса на основе raw_data
     */
    protected function determineRequestType(array $rawData): string
    {
        if (isset($rawData['audio']) || isset($rawData['voice']) || isset($rawData['speech'])) {
            return 'voice_processing';
        }
        
        if (isset($rawData['image']) || isset($rawData['photo']) || isset($rawData['vision'])) {
            return 'image_processing';
        }
        
        if (isset($rawData['text']) || isset($rawData['content'])) {
            return 'text_processing';
        }
        
        if (isset($rawData['file']) || isset($rawData['document'])) {
            return 'file_processing';
        }
        
        return 'unknown_processing';
    }

    /**
     * Продолжает цепочку фильтров после обработки внешних данных
     */
    protected function continueFilterChain(Filter $filter, array $processResult): void
    {
        try {
            $filterId = $filter->getFilterId();

            // 🔥 ВАЖНО: Проверяем, было ли сообщение обработано как команда
            $isCommand = $this->message->info['processed_as_command'] ?? false;
            
            if ($isCommand) {
                Log::info('🚫 Сообщение обработано как команда - НЕ продолжаем цепочку фильтров', [
                    'message_id' => $this->message->id,
                    'filter_id' => $filterId,
                    'command_id' => $this->message->info['command_id'] ?? 'unknown'
                ]);
                return; // ⛔ СТОП: команды не идут дальше по цепочке
            }

            $decision = $processResult['decision'] ?? Filter::DECISION_CONTINUE;
            $status = $processResult['status'] ?? Filter::STATUS_COMPLETED;

            if ($status === Filter::STATUS_COMPLETED && $decision === Filter::DECISION_CONTINUE) {
                Log::info('Продолжаем цепочку фильтров после успешного завершения', [
                    'message_id' => $this->message->id,
                    'filter_id' => $filterId,
                    'status' => $status,
                    'decision' => $decision
                ]);

                FilterProcessor::dispatchNextFilter($this->message, $filterId);
                return;
            }

            if (!Filter::shouldStopChain($processResult)) {
                Log::info('Продолжаем цепочку фильтров после обработки внешних данных', [
                    'message_id' => $this->message->id,
                    'filter_id' => $filterId
                ]);

                FilterProcessor::dispatchNextFilter($this->message, $filterId);
            } else {
                Log::info('Цепочка фильтров остановлена после обработки внешних данных', [
                    'message_id' => $this->message->id,
                    'filter_id' => $filterId,
                    'decision' => $decision,
                    'status' => $status
                ]);

                // ❌ УБИРАЕМ вызов finalizeMessageProcessing для команд
                // Команды уже помечены как обработанные и не должны проходить стандартное завершение
            }

        } catch (\Exception $e) {
            Log::error('Ошибка продолжения цепочки фильтров', [
                'message_id' => $this->message->id,
                'filter_id' => $filter->getFilterId(),
                'error' => $e->getMessage()
            ]);
        }
    }

}