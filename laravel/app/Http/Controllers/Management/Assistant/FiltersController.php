<?php
namespace App\Http\Controllers\Management\Assistant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

use App\Jobs\Assistant\Filters\GenerateFiltersList;
use App\Models\Assistant\FiltersModel;
use App\Models\Propertys;
use App\Helpers\Logs\Logs as Logator;

class FiltersController extends Controller
{
    public $answer = false;
    public $logator;

    public function index()
    {
        $filters = FiltersModel::orderByRaw('CAST((SELECT value FROM message_filters_propertys WHERE object_id = message_filters_objects.id AND property_id = 112) AS UNSIGNED) ASC')
            ->paginate(15);
            
        return view('management.assistant.filters.index', [
            'filters' => $filters
        ]);
    }

    public function create()
    {
        return view('management.assistant.filters.create');
    }

    public function store(Request $request)
    {
        $filter = new FiltersModel;
        $filter->save();
        $filter->propertys()->attach(1, ['value' => $request->filter]);
        
        // Устанавливаем порядок по умолчанию
        $maxOrder = FiltersModel::join('message_filters_propertys', 'message_filters_objects.id', '=', 'message_filters_propertys.object_id')
            ->where('message_filters_propertys.property_id', 112)
            ->max('message_filters_propertys.value');
            
        $filter->propertys()->attach(112, ['value' => ($maxOrder + 1)]);

        $this->logator = new Logator;
        $this->logator->setAuthor('Management Assistant FiltersController');
        $this->logator->setType('success');
        $this->logator->setText("Пользователь ".session()->get('user_id')." создал фильтр - $filter->id");
        $this->logator->write();

        return redirect()->route('m.assistant.filters.edit', $filter->id);
    }

    public function edit(Request $request, $id)
    {
        $filter = FiltersModel::find($id);
        return view('management.assistant.filters.edit', [
            'filter' => $filter,
            'urlForUpdate' => $this->getUrlForUpdate()
        ]);
    }

    public function show(Request $request, $id)
    {
        $object = FiltersModel::find($id);
        $roles = session()->get('roles');

        if(!in_array(20, $roles)) {
            return redirect('/');
        }

        $fields = $object->fields();
        $checked = [];
        $forShow = [];
        
        foreach($fields as $field) {
            $desc = json_decode($field->params)->desc ?? '';
            $property = $object->propertyById($field->property_id);
            
            if(is_null($property)) {
                $property = Propertys::find($field->property_id);
                $property->desc = $desc;
                $forShow[] = $property;
            } else {
                $forShow[] = $property;
            }
            $checked[] = $field->property_id;
        }

        $extra = $object->propertys()->whereNotIn('property_id', $checked)->get();
        foreach($extra as $field) {
            $forShow[] = $field;
        }

        return view('management.shower', [
            'object' => $object,
            'forShow' => $forShow
        ]);
    }

    public function getUrlForUpdate()
    {
        $current = Route::getCurrentRoute();
        $url = '';
        foreach($current->parameterNames as $param) {
            $url = $url.'/'.$param.'s';
            if(isset($current->parameters[$param])) {
                $url = $url.'/'.$current->parameters[$param];
            }
        }
        return '/'.Route::getCurrentRoute()->getPrefix().$url;
    }

    public function update(Request $request, $id)
    {
        $object = FiltersModel::find($id);
        
        if ($request->command == 'change-property') {
            $property = $object->propertyById($request->property_id);
            
            if (is_null($property)) {
                $object->propertys()->attach($request->property_id, ['value' => $request->value]);
            } else {
                $property->pivot->value = $request->value;
                $property->pivot->save();
            }
            
            if ($request->property_id == 102) {
                $this->updateFilterParameters($object, $request->value);
            }
            
            dispatch(new GenerateFiltersList());

            return response()->json(['status' => 'success']);
        }

        if ($request->command == 'get-parameters') {
            return $this->getParameters($id);
        }

        if ($request->command == 'save-parameters') {
            return $this->saveParameters($request, $object);
        }

        // Получение полной информации о фильтре для модального окна
        if ($request->command == 'get-settings') {
            return $this->getFullSettings($id);
        }

        // Сохранение всех настроек фильтра
        if ($request->command == 'save-settings') {
            return $this->saveFullSettings($request, $object);
        }

        return response()->json(['status' => 'error', 'message' => 'Unknown command']);
    }

    /**
     * Получение полной информации о фильтре для модального окна
     */
    protected function getFullSettings($id)
    {
        $filter = FiltersModel::find($id);
        if (!$filter) {
            return response()->json(['error' => 'Фильтр не найден'], 404);
        }

        try {
            $handlerClass = $filter->propertyById(108)->pivot->value ?? null;
            $hasHandler = $handlerClass && class_exists($handlerClass);
            
            // Основные настройки фильтра
            $basicSettings = [
                'name' => $filter->propertyById(1)->pivot->value ?? 'Без названия',
                'type' => $filter->propertyById(107)->pivot->value ?? 'handler',
                'handler' => $handlerClass ?? '',
                'description' => $filter->propertyById(109)->pivot->value ?? '',
                'order' => $filter->propertyById(112)->pivot->value ?? 0,
                'enabled' => filter_var($filter->propertyById(116)->pivot->value ?? false, FILTER_VALIDATE_BOOLEAN),
            ];

            // Параметры фильтра
            $parameters = [];
            $parametersStructure = [];
            
            if ($hasHandler) {
                $filterInstance = new $handlerClass();
                if (method_exists($filterInstance, 'getParametersStructure')) {
                    $parametersStructure = $filterInstance->getParametersStructure();
                }
            }

            // Текущие значения параметров
            $parametersProperty = $filter->propertyById(102);
            if ($parametersProperty) {
                $parameters = json_decode($parametersProperty->pivot->value, true) ?? [];
            }

            // Если параметров нет, используем значения по умолчанию
            if (empty($parameters) && !empty($parametersStructure)) {
                foreach ($parametersStructure as $key => $config) {
                    $parameters[$key] = $config['default'] ?? '';
                }
            }

            return response()->json([
                'success' => true,
                'filter' => [
                    'id' => $filter->id,
                    'basic_settings' => $basicSettings,
                    'parameters_structure' => $parametersStructure,
                    'parameters' => $parameters,
                    'has_handler' => $hasHandler,
                    'handler_name' => $hasHandler ? $handlerClass : 'Не указан'
                ]
            ]);

        } catch (\Throwable $e) {
            Log::error('Ошибка получения полных настроек фильтра', [
                'filter_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Ошибка загрузки настроек: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Сохранение всех настроек фильтра
     */
    protected function saveFullSettings(Request $request, FiltersModel $filter)
    {
        try {
            // Получаем данные из FormData
            $name = $request->input('name');
            $type = $request->input('type');
            $handler = $request->input('handler');
            $description = $request->input('description');
            $order = $request->input('order');
            $enabled = $request->input('enabled', 0);

            // Сохраняем основные настройки
            $this->updateProperty($filter, 1, $name);
            $this->updateProperty($filter, 107, $type);
            $this->updateProperty($filter, 108, $handler);
            $this->updateProperty($filter, 109, $description);
            $this->updateProperty($filter, 112, $order);
            $this->updateProperty($filter, 116, $enabled);

            // Получаем параметры из FormData
            $parameters = [];
            foreach ($request->all() as $key => $value) {
                if (strpos($key, 'parameters[') === 0) {
                    // Извлекаем имя параметра из parameters[paramName]
                    $paramName = substr($key, 11, -1); // Убираем 'parameters[' и ']'
                    $parameters[$paramName] = $value;
                }
            }

            // Сохраняем параметры
            $parametersJson = json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $this->updateProperty($filter, 102, $parametersJson);
            
            // Запускаем обновление списка фильтров
            dispatch(new GenerateFiltersList());
            
            Log::info('Все настройки фильтра сохранены', [
                'filter_id' => $filter->id,
                'name' => $name,
                'parameters_count' => count($parameters)
            ]);

            return response()->json([
                'success' => true, 
                'message' => 'Все настройки успешно сохранены'
            ]);

        } catch (\Throwable $e) {
            Log::error('Ошибка сохранения всех настроек фильтра', [
                'filter_id' => $filter->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false, 
                'message' => 'Ошибка сохранения настроек: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Вспомогательный метод для обновления свойства
     */
    protected function updateProperty(FiltersModel $filter, int $propertyId, $value)
    {
        $property = $filter->propertyById($propertyId);
        
        if (is_null($property)) {
            $filter->propertys()->attach($propertyId, ['value' => $value]);
        } else {
            $property->pivot->value = $value;
            $property->pivot->save();
        }
    }

    /**
     * Получение параметров фильтра
     */
    protected function getParameters($id)
    {
        $filter = FiltersModel::find($id);
        if (!$filter) {
            return response()->json(['error' => 'Фильтр не найден'], 404);
        }

        try {
            $handlerClass = $filter->propertyById(108)->pivot->value ?? null;
            $hasHandler = $handlerClass && class_exists($handlerClass);
            
            $parametersStructure = [];
            if ($hasHandler) {
                $filterInstance = new $handlerClass();
                if (method_exists($filterInstance, 'getParametersStructure')) {
                    $parametersStructure = $filterInstance->getParametersStructure();
                }
            }

            // Текущие значения параметров
            $currentParameters = [];
            $parametersProperty = $filter->propertyById(102);
            if ($parametersProperty) {
                $currentParameters = json_decode($parametersProperty->pivot->value, true) ?? [];
            }

            return response()->json([
                'success' => true,
                'parameters_structure' => $parametersStructure,
                'current_parameters' => $currentParameters,
                'has_handler' => $hasHandler
            ]);

        } catch (\Throwable $e) {
            Log::error('Ошибка получения параметров фильтра', [
                'filter_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Ошибка загрузки параметров: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Сохранение параметров фильтра
     */
    protected function saveParameters(Request $request, FiltersModel $filter)
    {
        try {
            $parameters = $request->all();
            
            // Убираем служебные поля
            unset($parameters['_token']);
            unset($parameters['_method']);
            unset($parameters['command']);
            
            $parametersJson = json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $parametersProperty = $filter->propertyById(102);
            
            if (is_null($parametersProperty)) {
                $filter->propertys()->attach(102, ['value' => $parametersJson]);
            } else {
                $parametersProperty->pivot->value = $parametersJson;
                $parametersProperty->pivot->save();
            }
            
            dispatch(new GenerateFiltersList());
            
            return response()->json([
                'success' => true, 
                'message' => 'Параметры успешно сохранены'
            ]);

        } catch (\Throwable $e) {
            Log::error('Ошибка сохранения параметров фильтра', [
                'filter_id' => $filter->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false, 
                'message' => 'Ошибка сохранения параметров: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Обновление параметров фильтра
     */
    protected function updateFilterParameters(FiltersModel $filter, $parametersJson)
    {
        // Дополнительная логика обновления параметров фильтра, если необходима
        // Этот метод может быть использован для дополнительной обработки параметров
        // после их сохранения
    }

    /**
     * Удаление фильтра
     */
    public function destroy($id)
    {
        try {
            $filter = FiltersModel::find($id);
            if ($filter) {
                $filter->propertys()->detach();
                $filter->delete();
                
                return response()->json(['success' => true]);
            }
            
            return response()->json(['success' => false, 'message' => 'Фильтр не найден'], 404);
            
        } catch (\Exception $e) {
            Log::error("Error deleting filter: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Ошибка при удалении'], 500);
        }
    }
}