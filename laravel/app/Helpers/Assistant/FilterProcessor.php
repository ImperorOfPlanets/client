<?php

namespace App\Helpers\Assistant;

use App\Models\Assistant\MessagesModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Jobs\Assistant\Filters\ProcessFilterJob;

/**
 * Класс для управления цепочкой фильтров сообщений
 *
 * - Загружает и сортирует активные фильтры
 * - Запускает фильтры по цепочке
 * - Обрабатывает результаты фильтров
 * - Управляет статусами сообщений
 */
class FilterProcessor
{
    /**
     * Получение списка активных фильтров
     * 
     * @return array
     */
    public static function getActiveFilters(): array
    {
        if (!Storage::disk('local')->exists('filters/filters.json')) {
            Log::error('❌ Файл filters.json не найден');
            return [];
        }
    
        try {
            $filtersJson = Storage::disk('local')->get('filters/filters.json');
            $filters = json_decode($filtersJson, true);
    
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('❌ Ошибка декодирования filters.json: ' . json_last_error_msg());
                return [];
            }
    
            // Сортируем фильтры по order (очередь выполнения)
            usort($filters, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
    
            Log::debug('📂 Загружены активные фильтры', [
                'количество' => count($filters),
                'фильтры' => array_column($filters, 'id', 'order')
            ]);
    
            return $filters;
    
        } catch (\Exception $e) {
            Log::error('❌ Ошибка чтения filters.json: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Запуск цепочки фильтров с первого фильтра
     */
    public static function startFilterChain(MessagesModel $message): void
    {
        $filters = self::getActiveFilters();
        
        if (empty($filters)) {
            Log::warning('⚠️ Нет активных фильтров для обработки', ['message_id' => $message->id]);
            return;
        }

        // Берём первый фильтр
        $firstFilter = $filters[0];
        self::dispatchFilterJob($message, $firstFilter);
        
        Log::info('▶️ Цепочка фильтров запущена', [
            'message_id' => $message->id,
            'первый_фильтр' => $firstFilter['id'] ?? 'unknown'
        ]);
    }

    /**
     * Запуск следующего фильтра после выполнения предыдущего
     */
    public static function dispatchNextFilter(MessagesModel $message, int $lastFilterId): void
    {
        $filters = self::getActiveFilters();
        $found = false;
    
        Log::debug('🔎 Поиск следующего фильтра', [
            'message_id' => $message->id,
            'последний_фильтр' => $lastFilterId,
            'все_фильтры' => array_column($filters, 'id')
        ]);
    
        foreach ($filters as $filter) {
            if ($found) {
                Log::info('➡️ Запуск следующего фильтра', [
                    'message_id' => $message->id,
                    'следующий_фильтр' => $filter['id']
                ]);
                
                self::dispatchFilterJob($message, $filter);
                return;
            }
    
            if ($filter['id'] === $lastFilterId) {
                $found = true;
                Log::debug('✅ Найден последний обработанный фильтр', [
                    'message_id' => $message->id,
                    'filter_id' => $lastFilterId
                ]);
            }
        }
    
        // Если больше фильтров нет
        if ($found) {
            Log::info('🏁 Все фильтры обработаны, завершаем цепочку', [
                'message_id' => $message->id
            ]);
            self::finalizeMessageProcessing($message);
        } else {
            Log::warning('⚠️ Следующий фильтр не найден', [
                'message_id' => $message->id,
                'last_filter_id' => $lastFilterId
            ]);
        }
    }

    /**
     * Диспатч job для выполнения фильтра
     */
    private static function dispatchFilterJob(MessagesModel $message, array $filter): void
    {
        $messageData = [
            'text' => $message->text,
            'chat_id' => $message->chat_id,
            'user_id' => $message->info['from'] ?? null,
            'user_name' => $message->info['name'] ?? 'Unknown',
            'is_group' => $message->info['is_group'] ?? false,
            'message_info' => $message->info
        ];
    
        ProcessFilterJob::dispatch(
            $filter['id'],
            $message->id,
            $messageData,
            self::class,
            'handleFilterResult'
        );
    
        Log::info('⚙️ Фильтр запущен', [
            'message_id' => $message->id,
            'filter_id' => $filter['id'],
            'тип' => $filter['type']
        ]);
    }

    /**
     * Callback — обработка результата фильтра
     */
    public static function handleFilterResult(int $filterId, int $messageId, array $result): void
    {
        Log::info('📩 Результат фильтра получен', [
            'filter_id' => $filterId,
            'message_id' => $messageId,
            'результат' => $result
        ]);
    
        $message = MessagesModel::find($messageId);
        if (!$message) {
            Log::error('❌ Сообщение не найдено для обновления результата', [
                'message_id' => $messageId,
                'filter_id' => $filterId
            ]);
            return;
        }

        // Если фильтр ждёт AI — цепочка стопается
        if (($result['status'] ?? '') === 'pending') {
            Log::info('🤖 Фильтр ожидает внешнего ресурса', [
                'filter_id' => $filterId,
                'message_id' => $messageId,
                'external_id' => $result['external_id'] ?? null
            ]);
        
            $info = $message->info;
            $info['filters'][$filterId] = [
                'status' => 'pending',
                'external_id' => $result['external_id'] ?? null,
                'updated_at' => now()->toDateTimeString()
            ];
            $message->info = $info;
            $message->save();
        
            return;
        }
        
    
        // Сохраняем результат фильтра
        $info = $message->info;
        $info['filters'][$filterId] = [
            'processed_at' => now()->toDateTimeString(),
            'result' => $result,
            'status' => 'completed'
        ];
        
        $message->info = $info;
        $message->save();
    
        Log::info('💾 Результат фильтра сохранён', [
            'message_id' => $messageId,
            'filter_id' => $filterId
        ]);

        // Если фильтр сказал "остановить цепочку" → финализируем
        if (($result['decision'] ?? '') === 'reject' || ($result['decision'] ?? '') === 'skip_processing') {
            Log::warning('⛔ Цепочка остановлена решением фильтра', [
                'message_id' => $messageId,
                'filter_id' => $filterId,
                'decision' => $result['decision']
            ]);
            self::finalizeMessageProcessing($message);
            return;
        }
    
        // Иначе — запускаем следующий фильтр
        self::dispatchNextFilter($message, $filterId);
    }

    /**
     * Завершение обработки сообщения
     */
    private static function finalizeMessageProcessing(MessagesModel $message): void
    {
        $approved = self::analyzeFilterResults($message->info['filters'] ?? []);
        
        $message->update([
            'status' => $approved ? 2 : 3, // 2 = одобрено, 3 = отклонено
            'info->approved' => $approved,
            'info->processed_at' => now()
        ]);

        Log::info($approved ? '✅ Сообщение одобрено' : '❌ Сообщение отклонено', [
            'message_id' => $message->id
        ]);
    }

    /**
     * Анализ результатов фильтров
     */
    public static function analyzeFilterResults(array $filtersResults): bool
    {
        if (empty($filtersResults)) {
            Log::warning('⚠️ Нет результатов фильтров для анализа');
            return false;
        }
        
        foreach ($filtersResults as $filterId => $result) {
            $filterResult = $result['result'] ?? []; // Получаем настоящий результат
            
            if (isset($filterResult['approved']) && $filterResult['approved'] === false) {
                Log::info('❌ Сообщение отклонено фильтром', ['filter_id' => $filterId]);
                return false;
            }
            if (($filterResult['decision'] ?? '') === 'skip_processing') {
                Log::info('⏭️ Обработка пропущена фильтром', ['filter_id' => $filterId]);
                return false;
            }
            if (($filterResult['decision'] ?? '') === 'reject') {
                Log::info('🚫 Сообщение отклонено фильтром по decision', ['filter_id' => $filterId]);
                return false;
            }
        }
        
        Log::info('✅ Сообщение одобрено всеми фильтрами');
        return true;
    }

    /**
     * Проверка длины сообщения
     */
    public static function checkMessageLength(MessagesModel $message, int $maxLength = 250): bool
    {
        if (mb_strlen($message->text) > $maxLength) {
            $message->update(['status' => 1]);
            Log::warning('📏 Сообщение превысило лимит длины', [
                'message_id' => $message->id,
                'length' => mb_strlen($message->text),
                'max_length' => $maxLength
            ]);
            return false;
        }
        return true;
    }

    /**
     * Проверка, что сообщение полностью обработано фильтрами
     */
    public static function isFiltered(MessagesModel $message): bool
    {
        return self::areAllFiltersCompleted($message);
    }

    /**
     * Проверка завершения всех фильтров
     */
    public static function areAllFiltersCompleted(MessagesModel $message): bool
    {
        $filters = self::getActiveFilters();
        $completedFilters = $message->info['filters'] ?? [];
        
        Log::debug('🔍 Проверка завершения фильтров', [
            'message_id' => $message->id,
            'active_filters_count' => count($filters),
            'completed_filters_count' => count($completedFilters)
        ]);
        
        // Если нет активных фильтров, считаем обработку завершенной
        if (empty($filters)) {
            Log::info('✅ Нет активных фильтров, обработка завершена', ['message_id' => $message->id]);
            return true;
        }
        
        // Проверяем, что все активные фильтры завершены
        foreach ($filters as $filter) {
            $filterId = $filter['id'];
            if (!isset($completedFilters[$filterId]) || 
                ($completedFilters[$filterId]['status'] ?? '') !== 'completed') {
                Log::debug('⏳ Фильтр еще не завершен', [
                    'message_id' => $message->id,
                    'filter_id' => $filterId,
                    'status' => $completedFilters[$filterId]['status'] ?? 'missing'
                ]);
                return false;
            }
        }
        
        Log::info('✅ Все фильтры завершены', ['message_id' => $message->id]);
        return true;
    }
}
