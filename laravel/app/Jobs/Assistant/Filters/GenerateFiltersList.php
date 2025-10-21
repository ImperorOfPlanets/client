<?php
namespace App\Jobs\Assistant\Filters;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use App\Models\Assistant\FiltersModel;

class GenerateFiltersList implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        $this->generateFiltersFile();
    }

    private function generateFiltersFile()
    {
        $filters = FiltersModel::with('propertys')
            ->get()
            ->map(function($filter) {
                // Получаем основные свойства
                $name = $filter->propertyById(1)?->pivot->value ?? 'Без названия';
                $type = $filter->propertyById(107)?->pivot->value;
                $value = $filter->propertyById(108)?->pivot->value;
                $order = (int)($filter->propertyById(112)?->pivot->value ?? 0);
                $enabled = (bool)($filter->propertyById(116)?->pivot->value ?? false);
                $description = $filter->propertyById(109)?->pivot->value ?? '';

                // Получаем параметры фильтра (property_id = 102)
                $parameters = $this->getFilterParameters($filter);

                // Формируем базовые параметры в зависимости от типа
                $baseParameters = [];
                if ($type === 'prompt') {
                    $baseParameters['prompt'] = $value;
                } elseif ($type === 'handler') {
                    $baseParameters['handler'] = $value;
                }

                // Объединяем базовые параметры и пользовательские параметры
                $mergedParameters = array_merge($baseParameters, $parameters);

                return [
                    'id' => $filter->id,
                    'name' => $name,
                    'type' => $type,
                    'order' => $order,
                    'enabled' => $enabled,
                    'description' => $description,
                    'parameters' => $mergedParameters
                ];
            })
            ->filter(function($filter) {
                // Фильтруем только включенные и с корректным типом
                return $filter['enabled'] && in_array($filter['type'], ['prompt', 'handler']);
            })
            ->sortBy('order') // Сортируем по порядку
            ->values()
            ->toArray();

        $json = json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // Сохраняем в storage/app/filters/
        Storage::disk('local')->put('filters/filters.json', $json);

        Log::info("GenerateFiltersList: filters.json создан", [
            'disk' => 'local',
            'path' => 'filters/filters.json',
            'count' => count($filters),
            'filters' => array_map(function($filter) {
                return [
                    'id' => $filter['id'],
                    'name' => $filter['name'],
                    'parameters_count' => count($filter['parameters'])
                ];
            }, $filters)
        ]);
    }

    /**
     * Получение параметров фильтра из свойства 102
     */
    private function getFilterParameters(FiltersModel $filter): array
    {
        $parametersProperty = $filter->propertyById(102);
        
        if (!$parametersProperty) {
            return [];
        }

        try {
            $parameters = json_decode($parametersProperty->pivot->value, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning("GenerateFiltersList: Ошибка декодирования параметров фильтра", [
                    'filter_id' => $filter->id,
                    'json_error' => json_last_error_msg(),
                    'raw_value' => $parametersProperty->pivot->value
                ]);
                return [];
            }

            // Валидация и очистка параметров
            return $this->validateParameters($parameters);

        } catch (\Throwable $e) {
            Log::error("GenerateFiltersList: Ошибка обработки параметров фильтра", [
                'filter_id' => $filter->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Валидация и очистка параметров
     */
    private function validateParameters(array $parameters): array
    {
        $validated = [];

        foreach ($parameters as $key => $value) {
            // Пропускаем пустые значения, кроме булевых и числовых нулей
            if ($value === '' || $value === null) {
                continue;
            }

            // Приводим булевы значения к правильному формату
            if (is_bool($value)) {
                $validated[$key] = $value;
                continue;
            }

            // Обработка строковых булевых значений
            if (is_string($value)) {
                $lowerValue = strtolower($value);
                if ($lowerValue === 'true' || $lowerValue === '1') {
                    $validated[$key] = true;
                    continue;
                } elseif ($lowerValue === 'false' || $lowerValue === '0') {
                    $validated[$key] = false;
                    continue;
                }
            }

            // Приводим числовые строки к числам
            if (is_numeric($value)) {
                $validated[$key] = (float)$value == (int)$value ? (int)$value : (float)$value;
                continue;
            }

            // Сохраняем остальные значения как есть
            $validated[$key] = $value;
        }

        return $validated;
    }
}