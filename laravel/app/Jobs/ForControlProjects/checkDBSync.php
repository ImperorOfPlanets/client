<?php

namespace App\Jobs\ForControlProjects;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

use App\Models\Core\Objects;
use App\Models\Core\Groups; 
use App\Models\Core\Propertys;

class CheckDBSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $params;
    public $object;
    public $projectConnection;
    public $logPath;
    public $logFileName;
    public $jsonLogFileName;
    public $jsonLog = [];

    public function __construct($params = null)
    {
        $this->params = $params;
    }

    /**
     * Определение имен файлов для логов
     */
    private function setupLogFileNames(): void
    {
        // Если имя файла передано в параметрах, используем его
        if (isset($this->params['log_file_name']) && !empty($this->params['log_file_name'])) {
            $baseName = $this->params['log_file_name'];
            
            // Убедимся, что расширение .log
            if (!str_ends_with($baseName, '.log')) {
                $baseName .= '.log';
            }
            
            $this->logFileName = $baseName;
            $this->jsonLogFileName = str_replace('.log', '.json', $baseName);
        } else {
            // Генерируем автоматическое имя
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $this->logFileName = $timestamp . '.log';
            $this->jsonLogFileName = $timestamp . '.json';
        }

        $this->writeLog("Используются файлы логов: {$this->logFileName} и {$this->jsonLogFileName}");
    }

    /**
     * Добавление различия в JSON лог
     */
    private function addDifference(array $difference): void
    {
        $difference['id'] = count($this->jsonLog['differences']) + 1;
        $difference['requires_action'] = true;
        $difference['user_selected'] = false;
        $difference['resolved'] = false;
        
        $this->jsonLog['differences'][] = $difference;
        $this->jsonLog['summary']['differences'][] = $difference['type'];
    }

    /**
     * Добавление проверки таблицы в JSON лог
     */
    private function addTableCheck(array $tableInfo): void
    {
        $this->jsonLog['tables_check'][] = $tableInfo;
    }

    /**
     * Добавление проверки группы в JSON лог
     */
    private function addGroupCheck(array $groupInfo): void
    {
        $this->jsonLog['groups_check'][] = $groupInfo;
    }

    private function getProjectDBConfig()
    {
        $config = [
            'host'     => $this->object->propertyById(86)->pivot->value ?? null,
            'port'     => $this->object->propertyById(87)->pivot->value ?? 3306,
            'database' => $this->object->propertyById(88)->pivot->value ?? null,
            'username' => $this->object->propertyById(89)->pivot->value ?? null,
            'password' => $this->object->propertyById(90)->pivot->value ?? null,
        ];

        $this->writeLog('Получены параметры БД: ' . json_encode($config, JSON_UNESCAPED_UNICODE));

        foreach (['host', 'database', 'username', 'password'] as $key) {
            if (empty($config[$key])) {
                $this->writeLog(
                    'ОТЛАДКА: Свойство ID ' .
                    (86 + array_search($key, ['host', 'port', 'database', 'username', 'password'])) .
                    ' не найдено или пустое',
                    'debug'
                );
                throw new \Exception("Отсутствует параметр БД: {$key}");
            }
        }

        return $config;
    }

    protected function writeLog(string $message, string $level = 'info'): void
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');
        $logEntry  = "[{$timestamp}] [{$level}] {$message}\n";

        $logPath = storage_path("logs/{$this->logPath}");
        if (!File::exists($logPath)) {
            File::makeDirectory($logPath, 0755, true);
        }

        File::append($logPath . '/' . $this->logFileName, $logEntry);
        Log::$level($message, ['project_id' => $this->object->id ?? null]);
    }

    private function logTable(string $title, array $headers, array $rows): void
    {
        $this->writeLog('');
        $this->writeLog(str_repeat('=', 80));
        $this->writeLog(strtoupper($title));
        $this->writeLog(str_repeat('=', 80));

        $headerLine = '| ';
        $separator  = '| ';
        foreach ($headers as $header) {
            $headerLine .= str_pad($header, 20) . ' | ';
            $separator  .= str_repeat('-', 20) . ' | ';
        }
        $this->writeLog($headerLine);
        $this->writeLog($separator);

        foreach ($rows as $row) {
            $line = '| ';
            foreach ($row as $cell) {
                $cellStr = (string)$cell;
                $line   .= str_pad(substr($cellStr, 0, 20), 20) . ' | ';
            }
            $this->writeLog($line);
        }
        $this->writeLog(str_repeat('=', 80));
        $this->writeLog('');
    }

    private function logSection(string $title): void
    {
        $this->writeLog('');
        $this->writeLog(str_repeat('=', 80));
        $this->writeLog(strtoupper($title));
        $this->writeLog(str_repeat('=', 80));
    }

    private function accessDiffers($coreAccess, $projectAccess): bool
    {
        $normalizeAccess = function ($access) {
            if (!is_array($access)) {
                return [];
            }

            $result = [];
            foreach (['show', 'edit'] as $type) {
                if (isset($access[$type])) {
                    if (is_array($access[$type])) {
                        sort($access[$type]);
                        $result[$type] = $access[$type];
                    } else {
                        $result[$type] = [$access[$type]];
                    }
                }
            }
            return $result;
        };

        $normalizedCore    = $normalizeAccess($coreAccess);
        $normalizedProject = $normalizeAccess($projectAccess);

        return $normalizedCore !== $normalizedProject;
    }

    private function setupProjectConnection(array $config): void
    {
        config([
            'database.connections.project_temp' => [
                'driver'    => 'mysql',
                'host'      => $config['host'],
                'port'      => $config['port'],
                'database'  => $config['database'],
                'username'  => $config['username'],
                'password'  => $config['password'],
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix'    => '',
                'strict'    => true,
                'engine'    => null,
            ],
        ]);

        $this->projectConnection = DB::connection('project_temp');

        try {
            $this->projectConnection->getPdo();
            $this->writeLog('Успешное подключение к БД проекта');
        } catch (\Exception $e) {
            throw new \Exception('Не удалось подключиться к БД проекта: ' . $e->getMessage());
        }
    }

    private function checkDatabaseTables(): void
    {
        $this->logSection('ПРОВЕРКА СУЩЕСТВУЮЩИХ ТАБЛИЦ В БД');

        try {
            $tableList = $this->projectConnection->select('SHOW TABLES');
            $tables = array_map(fn($t) => array_values((array)$t)[0], $tableList);

            $tableInfo = [];
            foreach ($tables as $table) {
                $columns = $this->projectConnection->getSchemaBuilder()->getColumnListing($table);
                $tableInfo[] = [
                    $table,
                    count($columns),
                    implode(', ', array_slice($columns, 0, 3)) . '...',
                ];

                // JSON логирование таблицы
                $this->addTableCheck([
                    'table_name' => $table,
                    'columns_count' => count($columns),
                    'columns' => $columns,
                    'status' => 'exists'
                ]);
            }

            if (count($tableInfo) === 0) {
                $this->writeLog('Таблицы в БД проекта не найдены', 'warning');
            } else {
                $this->logTable('СУЩЕСТВУЮЩИЕ ТАБЛИЦЫ', ['Таблица', 'Колонок', 'Первые колонки'], $tableInfo);
            }
        } catch (\Exception $e) {
            $this->writeLog('Ошибка при получении списка таблиц: ' . $e->getMessage(), 'error');
        }
    }

    private function checkAllGroups(): void
    {
        $allGroups = Groups::all();
        $this->logSection('ПРОВЕРКА ВСЕХ ГРУПП СИСТЕМЫ');
        $this->writeLog('Найдено групп: ' . $allGroups->count());

        $this->jsonLog['summary']['total_groups'] = $allGroups->count();

        $summary = [];

        foreach ($allGroups as $group) {
            $groupResult = $this->checkSingleGroup($group);
            $summary[] = $groupResult;
            
            // JSON логирование результата проверки группы
            $this->addGroupCheck([
                'group_id' => $group->id,
                'group_name' => $group->name ?? 'Без имени',
                'export_enabled' => (bool)($group->paramById(1)->pivot->value ?? null),
                'table_prefix' => $group->params()->where('params.id', 2)->first()->pivot->value ?? null,
                'status' => $groupResult[2],
                'reason' => $groupResult[3],
                'errors' => $groupResult[4],
                'warnings' => $groupResult[5]
            ]);
        }

        $this->jsonLog['summary']['checked_groups'] = count(array_filter($summary, fn($s) => $s[2] === 'Проверено'));

        $this->logTable(
            'ИТОГИ ПРОВЕРКИ ВСЕХ ГРУПП',
            ['ID', 'Название', 'Статус', 'Причина', 'Ошибки', 'Предупреждения'],
            $summary
        );
    }

    private function checkSingleGroup(Groups $group): array
    {
        $groupId = $group->id;
        $name    = $group->name ?? 'Без имени';

        $param1 = $group->paramById(1)->pivot->value ?? null;
        $exportFlag = filter_var($param1, FILTER_VALIDATE_BOOLEAN);

        if (!$exportFlag) {
            $this->writeLog("Группа ID={$groupId} ({$name}) не предназначена к экспорту (параметр 1=false). Пропуск.");
            return [$groupId, $name, 'Пропущено', 'Экспорт отключён (параметр 1=false)', 0, 0];
        }

        $prefix = $group->params()->where('params.id', 2)->first()->pivot->value ?? null;
        if (empty($prefix)) {
            $this->writeLog("Группа ID={$groupId} ({$name}) пропущена. Нет параметра 2 (префикс таблиц).");
            return [$groupId, $name, 'Пропущено', 'Отсутствует префикс таблиц (параметр 2)', 0, 0];
        }

        $this->logSection("Проверка группы ID={$groupId} ({$name}), префикс '{$prefix}'");

        $errors = 0;
        $warnings = 0;

        // Проверяем структуру и таблицы для группы
        $structureResult = $this->checkDatabaseStructureForGroup($group, $prefix);
        $errors += $structureResult['errors'];
        $warnings += $structureResult['warnings'];

        // Проверяем свойства группы
        $propertiesResult = $this->checkGroupProperties($group, $prefix);
        $errors += $propertiesResult['errors'];
        $warnings += $propertiesResult['warnings'];

        // Проверяем объекты группы
        $objectsResult = $this->checkGroupObjects($group, $prefix);
        $errors += $objectsResult['errors'];
        $warnings += $objectsResult['warnings'];

        return [$groupId, $name, 'Проверено', '', $errors, $warnings];
    }

    private function checkDatabaseStructureForGroup(Groups $group, string $prefix): array
    {
        $this->writeLog("Проверка структуры БД для группы {$group->name} (ID: {$group->id}), префикс: {$prefix}");

        $errors = 0;
        $warnings = 0;

        $tables = [
            "{$prefix}_objects"   => ['id', 'params', 'created_at', 'updated_at', 'deleted_at'],
            "{$prefix}_propertys" => ['id', 'object_id', 'property_id', 'value', 'params'],
            "{$prefix}_fields"    => ['id', 'property_id', 'params'],
        ];

        foreach ($tables as $table => $requiredCols) {
            if (!$this->projectConnection->getSchemaBuilder()->hasTable($table)) {
                $this->writeLog("ВНИМАНИЕ: Таблица {$table} отсутствует.", 'warning');
                $warnings++;
                
                $this->addDifference([
                    'type' => 'missing_table',
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'table_name' => $table,
                    'severity' => 'warning',
                    'description' => "Отсутствует таблица {$table} для группы {$group->name}",
                    'suggested_fix' => "Создать таблицу {$table} с необходимыми колонками"
                ]);
                continue;
            }
            
            $this->writeLog("Таблица {$table} найдена, проверяем колонки...");

            try {
                $existingCols = $this->projectConnection->getSchemaBuilder()->getColumnListing($table);
            } catch (\Exception $e) {
                $this->writeLog("Ошибка при получении колонок таблицы {$table}: " . $e->getMessage(), 'error');
                $errors++;
                continue;
            }

            foreach ($requiredCols as $col) {
                if (!in_array($col, $existingCols)) {
                    $this->writeLog("ОТСУТСТВУЕТ КОЛОНКА: {$col} в таблице {$table}", 'warning');
                    $warnings++;
                    
                    $this->addDifference([
                        'type' => 'missing_column',
                        'group_id' => $group->id,
                        'group_name' => $group->name,
                        'table_name' => $table,
                        'column_name' => $col,
                        'severity' => 'warning',
                        'description' => "В таблице {$table} отсутствует колонка {$col}",
                        'suggested_fix' => "Добавить колонку {$col} в таблицу {$table}"
                    ]);
                }
            }

            // Проверка наличия первичного ключа (PK)
            try {
                $indexes = $this->projectConnection->select("SHOW INDEX FROM {$table}");
                $hasPk = collect($indexes)->contains(fn($idx) => $idx->Key_name === 'PRIMARY');
            
                if (!$hasPk) {
                    $this->writeLog("ПРЕДУПРЕЖДЕНИЕ: Таблица {$table} не имеет PRIMARY KEY", 'warning');
                    $warnings++;
                    
                    $this->addDifference([
                        'type' => 'missing_primary_key',
                        'group_id' => $group->id,
                        'group_name' => $group->name,
                        'table_name' => $table,
                        'severity' => 'warning',
                        'description' => "Таблица {$table} не имеет первичного ключа",
                        'suggested_fix' => "Добавить PRIMARY KEY для таблицы {$table}"
                    ]);
                }
            } catch (\Throwable $e) {
                $this->writeLog("Не удалось проверить индексы таблицы {$table}: " . $e->getMessage(), 'debug');
            }
        }

        // Дополнительная проверка на согласованность с основной таблицей propertys
        $consistencyResult = $this->checkBasicDataConsistency($prefix, $group);
        $warnings += $consistencyResult['warnings'];

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function checkBasicDataConsistency(string $prefix, Groups $group): array
    {
        $this->writeLog("Проверка согласованности базовых данных с префиксом {$prefix}");
        
        $warnings = 0;

        if (!$this->projectConnection->getSchemaBuilder()->hasTable('propertys')) {
            $this->writeLog('ОСНОВНАЯ ТАБЛИЦА propertys ОТСУТСТВУЕТ', 'warning');
            $warnings++;
            return ['warnings' => $warnings];
        }

        try {
            if ($this->projectConnection->getSchemaBuilder()->hasTable("{$prefix}_fields")) {
                $fieldPropertyIds = $this->projectConnection->table("{$prefix}_fields")->pluck('property_id')->unique()->toArray();

                if (!empty($fieldPropertyIds)) {
                    $missing = [];
                    foreach ($fieldPropertyIds as $pid) {
                        $exists = $this->projectConnection->table('propertys')->where('id', $pid)->exists();
                        if (!$exists) {
                            $missing[] = $pid;
                        }
                    }

                    if (!empty($missing)) {
                        $this->writeLog(
                            "РАСХОЖДЕНИЕ: В {$prefix}_fields есть property_id, отсутствующие в таблице propertys: " .
                            implode(',', $missing),
                            'warning'
                        );
                        $warnings++;
                        
                        $this->addDifference([
                            'type' => 'inconsistent_property_ids',
                            'group_id' => $group->id,
                            'group_name' => $group->name,
                            'table_name' => "{$prefix}_fields",
                            'missing_property_ids' => $missing,
                            'severity' => 'warning',
                            'description' => "В таблице {$prefix}_fields найдены property_id, отсутствующие в основной таблице propertys",
                            'suggested_fix' => "Добавить отсутствующие property_id в таблицу propertys или удалить их из {$prefix}_fields"
                        ]);
                    } else {
                        $this->writeLog("Все property_id из {$prefix}_fields присутствуют в таблице propertys");
                    }
                }
            }
        } catch (\Exception $e) {
            $this->writeLog("Ошибка при проверке согласованности базовых данных: " . $e->getMessage(), 'error');
        }

        return ['warnings' => $warnings];
    }

    private function checkGroupProperties(Groups $group, string $prefix): array
    {
        $this->writeLog("Проверка свойств группы ID={$group->id}...");

        $errors = 0;
        $warnings = 0;

        $propertiesToExport = $group->propertys()->wherePivot('block', 0)->get();

        $this->writeLog("Найдено свойств для экспорта: " . $propertiesToExport->count());

        if ($propertiesToExport->isEmpty()) {
            $this->writeLog("Нет свойств для экспорта в группе {$group->id}");
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        foreach ($propertiesToExport as $property) {
            $propertyResult = $this->checkProperty($group, $property, $prefix);
            $errors += $propertyResult['errors'];
            $warnings += $propertyResult['warnings'];
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function checkProperty(Groups $group, $property, string $prefix): array
    {
        $this->writeLog("Проверка свойства: {$property->name} (ID: {$property->id}) в группе {$group->id}");

        $errors = 0;
        $warnings = 0;

        $coreRequire = (bool)($property->pivot->require ?? false);
        $coreDesc = $property->pivot->desc ?? $property->desc ?? '';
        $coreAccess = [];
        if (!empty($property->pivot->access) && $this->isJson($property->pivot->access)) {
            $coreAccess = json_decode($property->pivot->access, true) ?: [];
        }

        if (!$this->projectConnection->getSchemaBuilder()->hasTable("{$prefix}_fields")) {
            $this->writeLog("ТАБЛИЦА ОТСУТСТВУЕТ: {$prefix}_fields", 'warning');
            $warnings++;
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        try {
            $field = $this->projectConnection->table("{$prefix}_fields")
                ->where('property_id', $property->id)
                ->first();
        } catch (\Exception $e) {
            $this->writeLog("Ошибка доступа к таблице {$prefix}_fields: " . $e->getMessage(), 'error');
            $errors++;
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        if (!$field) {
            $this->writeLog("Свойство {$property->name} (ID:{$property->id}) отсутствует в таблице {$prefix}_fields", 'warning');
            $warnings++;
            
            $this->addDifference([
                'type' => 'missing_property',
                'group_id' => $group->id,
                'group_name' => $group->name,
                'property_id' => $property->id,
                'property_name' => $property->name,
                'table_name' => "{$prefix}_fields",
                'severity' => 'warning',
                'description' => "Свойство {$property->name} отсутствует в таблице fields проекта",
                'suggested_fix' => "Добавить свойство {$property->id} в таблицу {$prefix}_fields"
            ]);
            
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $fieldParams = [];
        if (!empty($field->params) && $this->isJson($field->params)) {
            $fieldParams = json_decode($field->params, true) ?: [];
        }

        $projectRequire = (bool)($fieldParams['require'] ?? false);
        $projectDesc    = $fieldParams['desc'] ?? '';
        $projectAccess  = $fieldParams['access'] ?? [];

        // Сравнения
        if ($projectRequire && !$coreRequire) {
            $this->writeLog("РАСХОЖДЕНИЕ: Свойство {$property->name} обязательно в project, но не в core.", 'warning');
            $warnings++;
            
            $this->addDifference([
                'type' => 'property_require_mismatch',
                'group_id' => $group->id,
                'group_name' => $group->name,
                'property_id' => $property->id,
                'property_name' => $property->name,
                'core_value' => $coreRequire,
                'project_value' => $projectRequire,
                'severity' => 'warning',
                'description' => "Несоответствие обязательности свойства {$property->name}",
                'suggested_fix' => "Синхронизировать параметр require для свойства {$property->id}"
            ]);
        } elseif (!$projectRequire && $coreRequire) {
            $this->writeLog("РАСХОЖДЕНИЕ: Свойство {$property->name} обязательно в core, но не в project.", 'warning');
            $warnings++;
            
            $this->addDifference([
                'type' => 'property_require_mismatch',
                'group_id' => $group->id,
                'group_name' => $group->name,
                'property_id' => $property->id,
                'property_name' => $property->name,
                'core_value' => $coreRequire,
                'project_value' => $projectRequire,
                'severity' => 'warning',
                'description' => "Несоответствие обязательности свойства {$property->name}",
                'suggested_fix' => "Синхронизировать параметр require для свойства {$property->id}"
            ]);
        }

        if (trim((string)$coreDesc) !== trim((string)$projectDesc)) {
            $this->writeLog("РАСХОЖДЕНИЕ: Описание свойства {$property->name} различается", 'warning');
            $warnings++;
            
            $this->addDifference([
                'type' => 'property_description_mismatch',
                'group_id' => $group->id,
                'group_name' => $group->name,
                'property_id' => $property->id,
                'property_name' => $property->name,
                'core_value' => $coreDesc,
                'project_value' => $projectDesc,
                'severity' => 'warning',
                'description' => "Несоответствие описания свойства {$property->name}",
                'suggested_fix' => "Синхронизировать описание для свойства {$property->id}"
            ]);
        }

        if ($this->accessDiffers($coreAccess, $projectAccess)) {
            $this->writeLog("РАСХОЖДЕНИЕ: Права доступа свойства {$property->name} различаются", 'warning');
            $warnings++;
            
            $this->addDifference([
                'type' => 'property_access_mismatch',
                'group_id' => $group->id,
                'group_name' => $group->name,
                'property_id' => $property->id,
                'property_name' => $property->name,
                'core_value' => $coreAccess,
                'project_value' => $projectAccess,
                'severity' => 'warning',
                'description' => "Несоответствие прав доступа для свойства {$property->name}",
                'suggested_fix' => "Синхронизировать права доступа для свойства {$property->id}"
            ]);
        }

        $this->writeLog("Сверка свойства {$property->name} завершена");
        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function checkGroupObjects(Groups $group, string $prefix): array
    {
        $this->writeLog("Проверка объектов группы {$group->id}...");

        $errors = 0;
        $warnings = 0;

        $objectsToExport = $group->objects()
            ->whereHas('params', function ($query) {
                $query->where('params.id', 1)->where('objects_params.value', 'true');
            })->get();

        $this->writeLog("Объектов для проверки в группе: " . $objectsToExport->count());

        foreach ($objectsToExport as $coreObject) {
            $objectResult = $this->checkObject($coreObject, $prefix, $group);
            $errors += $objectResult['errors'];
            $warnings += $objectResult['warnings'];
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function checkObject($coreObject, string $prefix, Groups $group): array
    {
        $title = $coreObject->propertyById(1)->pivot->value ?? 'Без названия';
        $this->writeLog("Проверка объекта: {$title} (ID: {$coreObject->id}), префикс: {$prefix}");

        $errors = 0;
        $warnings = 0;

        try {
            $projectObject = $this->projectConnection->table("{$prefix}_objects")
                ->where('id', $coreObject->id)
                ->first();
        } catch (\Exception $e) {
            $this->writeLog("Ошибка доступа к таблице {$prefix}_objects: " . $e->getMessage(), 'error');
            $errors++;
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        if (!$projectObject) {
            $this->writeLog("Объект ID {$coreObject->id} отсутствует в project DB ({$prefix}_objects)", 'warning');
            $warnings++;
            
            $this->addDifference([
                'type' => 'missing_object',
                'group_id' => $group->id,
                'group_name' => $group->name,
                'object_id' => $coreObject->id,
                'object_name' => $title,
                'table_name' => "{$prefix}_objects",
                'severity' => 'warning',
                'description' => "Объект {$title} отсутствует в БД проекта",
                'suggested_fix' => "Добавить объект {$coreObject->id} в таблицу {$prefix}_objects"
            ]);
            
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $coreProperties = $coreObject->propertys()->wherePivot('block', 0)->get();
        foreach ($coreProperties as $property) {
            $propertyResult = $this->checkObjectProperty($coreObject, $property, $prefix, $group);
            $errors += $propertyResult['errors'];
            $warnings += $propertyResult['warnings'];
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function checkObjectProperty($coreObject, $property, string $prefix, Groups $group): array
    {
        $corePivot = $coreObject->propertys()->where('property_id', $property->id)->first();
        $corePivot = $corePivot ? $corePivot->pivot : null;

        if (!$corePivot) {
            $this->writeLog("Нет pivot-данных для свойства {$property->name} у объекта {$coreObject->id}", 'warning');
            return ['errors' => 0, 'warnings' => 1];
        }

        if (!empty($corePivot->lock)) {
            $this->writeLog("Значение свойства {$property->name} заблокировано для экспорта (object {$coreObject->id})");
            return ['errors' => 0, 'warnings' => 0];
        }

        $errors = 0;
        $warnings = 0;

        $coreValue = $corePivot->value ?? null;
        $coreAccess = [];
        if (!empty($corePivot->access) && $this->isJson($corePivot->access)) {
            $coreAccess = json_decode($corePivot->access, true) ?: [];
        }

        try {
            $projectRow = $this->projectConnection->table("{$prefix}_propertys")
                ->where('object_id', $coreObject->id)
                ->where('property_id', $property->id)
                ->first();
        } catch (\Exception $e) {
            $this->writeLog("Ошибка доступа к таблице {$prefix}_propertys: " . $e->getMessage(), 'error');
            return ['errors' => 1, 'warnings' => 0];
        }

        if (!$projectRow) {
            $this->writeLog("Свойство {$property->name} отсутствует у объекта {$coreObject->id} в БД проекта", 'warning');
            $warnings++;
            
            $this->addDifference([
                'type' => 'missing_object_property',
                'group_id' => $group->id,
                'group_name' => $group->name,
                'object_id' => $coreObject->id,
                'object_name' => $coreObject->propertyById(1)->pivot->value ?? 'Без названия',
                'property_id' => $property->id,
                'property_name' => $property->name,
                'table_name' => "{$prefix}_propertys",
                'severity' => 'warning',
                'description' => "Свойство {$property->name} отсутствует у объекта в БД проекта",
                'suggested_fix' => "Добавить свойство {$property->id} для объекта {$coreObject->id} в таблицу {$prefix}_propertys"
            ]);
            
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $projectValue = $projectRow->value ?? null;
        $projectAccess = [];
        if (!empty($projectRow->params) && $this->isJson($projectRow->params)) {
            $decoded = json_decode($projectRow->params, true) ?: [];
            $projectAccess = $decoded['access'] ?? [];
        }

        // Гибкое сравнение значений (1 == "1" считаются равными)
        if ($coreValue != $projectValue) {
            $this->writeLog(
                "РАСХОЖДЕНИЕ: Значение свойства '{$property->name}' (ID:{$property->id}) для объекта {$coreObject->id}. " .
                "Core: '{$coreValue}' | Project: '{$projectValue}'",
                'warning'
            );
            $warnings++;
            
            $this->addDifference([
                'type' => 'object_property_value_mismatch',
                'group_id' => $group->id,
                'group_name' => $group->name,
                'object_id' => $coreObject->id,
                'object_name' => $coreObject->propertyById(1)->pivot->value ?? 'Без названия',
                'property_id' => $property->id,
                'property_name' => $property->name,
                'core_value' => $coreValue,
                'project_value' => $projectValue,
                'severity' => 'warning',
                'description' => "Несоответствие значения свойства {$property->name} у объекта",
                'suggested_fix' => "Обновить значение свойства {$property->id} для объекта {$coreObject->id} в таблице {$prefix}_propertys"
            ]);
        }

        if ($this->accessDiffers($coreAccess, $projectAccess)) {
            $this->writeLog("РАСХОЖДЕНИЕ: Права доступа для свойства '{$property->name}' у объекта {$coreObject->id} различаются", 'warning');
            $warnings++;
            
            $this->addDifference([
                'type' => 'object_property_access_mismatch',
                'group_id' => $group->id,
                'group_name' => $group->name,
                'object_id' => $coreObject->id,
                'object_name' => $coreObject->propertyById(1)->pivot->value ?? 'Без названия',
                'property_id' => $property->id,
                'property_name' => $property->name,
                'core_value' => $coreAccess,
                'project_value' => $projectAccess,
                'severity' => 'warning',
                'description' => "Несоответствие прав доступа для свойства {$property->name} у объекта",
                'suggested_fix' => "Обновить права доступа для свойства {$property->id} у объекта {$coreObject->id}"
            ]);
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function isJson($string): bool
    {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function initJsonLog(): void
    {
        $this->jsonLog = [
            'metadata' => [
                'project_id' => $this->object->id ?? null,
                'project_name' => $this->object->propertyById(1)->pivot->value ?? 'Без названия',
                'started_at' => Carbon::now()->toISOString(),
                'status' => 'in_progress',
                'log_type' => 'db_sync_check'
            ],
            'summary' => [
                'total_groups' => 0,
                'checked_groups' => 0,
                'errors' => 0,
                'warnings' => 0,
                'differences' => []
            ],
            'tables_check' => [],
            'groups_check' => [],
            'differences' => []
        ];
    }

    /**
     * Сохранение JSON лога в файл
     */
    private function saveJsonLog(): void
    {
        // УБРАТЬ автоматическую установку статуса - это должно делаться явно
        $this->jsonLog['metadata']['finished_at'] = Carbon::now()->toISOString();
        
        if (isset($this->jsonLog['metadata']['started_at'])) {
            $this->jsonLog['metadata']['duration_seconds'] = 
                Carbon::parse($this->jsonLog['metadata']['started_at'])->diffInSeconds(Carbon::now());
        }
    
        $jsonContent = json_encode($this->jsonLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        $jsonPath = storage_path("logs/{$this->logPath}/{$this->jsonLogFileName}");
        File::put($jsonPath, $jsonContent);
        
        $this->writeLog("JSON лог сохранен: {$this->jsonLogFileName}");
    }

    /**
     * Установка статуса завершения
     */
    private function setCompletionStatus(string $status, string $message = null): void
    {
        $this->jsonLog['metadata']['status'] = $status;
        if ($message) {
            $this->jsonLog['metadata']['message'] = $message;
        }
    }

    public function handle(): void
    {
        $this->object = Objects::find($this->params['id']);
        if (!$this->object) {
            $this->writeLog('Объект не найден по переданному ID', 'error');
            return;
        }

        $this->logPath     = 'syncs/' . $this->object->id;
        $this->setupLogFileNames();
        Storage::makeDirectory($this->logPath);
        $this->initJsonLog();

        $this->writeLog(
            'Запущен процесс проверки БД проекта ' . $this->object->id .
            ' (' . ($this->object->propertyById(1)->pivot->value ?? 'Без названия') . ')'
        );

        try {
            $dbConfig = $this->getProjectDBConfig();
            $this->setupProjectConnection($dbConfig);

            $this->checkDatabaseTables();
            $this->checkAllGroups();

            // ЯВНО устанавливаем статус завершения
            $this->setCompletionStatus('completed', 'Проверка БД проекта завершена успешно');
            $this->writeLog('Проверка БД проекта завершена успешно');
            
        } catch (\Exception $e) {
            // ЯВНО устанавливаем статус ошибки
            $this->setCompletionStatus('failed', 'Критическая ошибка при проверке БД: ' . $e->getMessage());
            $this->writeLog('Критическая ошибка при проверке БД: ' . $e->getMessage(), 'error');
            throw $e;
        } finally {
            $this->saveJsonLog();
            if (isset($this->projectConnection)) {
                DB::disconnect('project_temp');
            }
        }
    }

    public function failed(\Exception $exception): void
    {
        // Убеждаемся, что статус установлен как 'failed'
        $this->setCompletionStatus('failed', 'Задача завершена с ошибкой: ' . $exception->getMessage());
        $this->writeLog('Задача проверки БД завершилась с ошибкой: ' . $exception->getMessage(), 'error');
        
        // Сохраняем JSON лог с правильным статусом
        $this->saveJsonLog();
    }
}