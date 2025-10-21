<?php
namespace App\Http\Controllers\Management\Assistant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use App\Models\Assistant\CommandsModel;
use App\Models\Socials\SocialsModel;
use App\Models\Propertys;

use App\Jobs\Keywords\GenKeywords;
use App\Jobs\Assistant\Commands\GenerateCommandsList;

use App\Helpers\Logs\Logs as Logator;

class CommandsController extends Controller
{
    public $answer = false;
    public $logator;

    public function index()
    {
        $commands = CommandsModel::paginate(15);
        return view('management.assistant.commands.index', [
            'commands' => $commands
        ]);
    }

    public function create()
    {
        return view('management.assistant.commands.create');
    }

    public function store(Request $request)
    {
        $command = new CommandsModel;
        $command->save();
        $command->propertys()->attach(1, ['value' => $request->command]);

        // Запускаем генерацию списка команд после создания

        $this->logator = new Logator;
        $this->logator->setAuthor('Management Assistant CommandsController');
        $this->logator->setType('success');
        $this->logator->setText("Пользователь " . session()->get('user_id') . " создал команду - $command->id");
        $this->logator->write();

        return redirect()->route('m.assistant.commands.edit', $command->id);
    }

    public function edit(Request $request, $id)
    {
        // Получаем команду
        $command = CommandsModel::find($id);
        
        // Получаем соц сети которые включены
        $socials = SocialsModel::with('propertys')
            ->whereHas('propertys', function($q) {
                $q->where('propertys.id', 116);
            })->get();
        
        // Проверяем установку
        foreach ($socials as $k => $social) {
            $on = $social->propertys()->where('property_id', 116)->first()->pivot->value ?? null;
            if (!filter_var($on, FILTER_VALIDATE_BOOLEAN)) {
                unset($socials[$k]);
            }
        }
        
        return view('management.assistant.commands.edit', [
            'command' => $command,
            'socials' => $socials,
            'urlForUpdate' => $this->getUrlForUpdate()
        ]);
    }

    // Показывает все настройки
    public function show(Request $request, $id)
    {
        // Объект показа
        $object = CommandsModel::find($id);

        // Получаем массив ролей
        $roles = session()->get('roles');

        // Отправляем не администраторов
        if (!in_array(20, $roles)) {
            return redirect('/');
        }

        // Получаем все стандартные поля группы
        $fields = $object->fields();

        // Проверенные свойства
        $checked = [];
        $forShow = [];
        
        foreach ($fields as $field) {
            // Получаем описание
            $desc = json_decode($field->params)->desc ?? '';

            // Получаем свойство
            $property = $object->propertyById($field->property_id);
            if (is_null($property)) {
                $property = Propertys::find($field->property_id);
                $property->desc = $desc;
                $forShow[] = $property;
            } else {
                $forShow[] = $property;
            }
            $checked[] = $field->property_id;
        }

        // Получаем свойства дополнительные объекта
        $extra = $object->propertys()->whereNotIn('property_id', $checked)->get();
        foreach ($extra as $field) {
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
        foreach ($current->parameterNames as $param) {
            $url = $url . '/' . $param . 's';
            if (isset($current->parameters[$param])) {
                $url = $url . '/' . $current->parameters[$param];
            }
        }
        return '/' . Route::getCurrentRoute()->getPrefix() . $url;
    }

    public function update(Request $request, $id)
    {
        $object = CommandsModel::find($id);
        
        if ($request->command == 'change-property') {
            $property = $object->propertyById($request->property_id);
            
            if (is_null($property)) {
                $object->propertys()->attach($request->property_id, ['value' => $request->value]);
            } else {
                $property->pivot->value = $request->value;
                $property->pivot->save();
            }
            
            // Запускаем оба джоба после изменения свойства
            dispatch(new GenKeywords());
            dispatch( new GenerateCommandsList());

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false], 400);
    }

    // Получаем результат выполнения команды
    public function getAnswer($text)
    {
        try {
            // Ищем команду по ключевым словам
            $commands = CommandsModel::with('propertys')
                ->whereHas('propertys', function($query) use ($text) {
                    $query->where(function($q) use ($text) {
                        // Основная команда (property_id = 1)
                        $q->where('property_id', 1)
                          ->where('value', 'like', '%' . $text . '%');
                    })->orWhere(function($q) use ($text) {
                        // Ключевые слова (property_id = 8)
                        $q->where('property_id', 8)
                          ->where('value', 'like', '%' . $text . '%');
                    });
                })
                ->where('active', true)
                ->first();

            if ($commands) {
                // Получаем тип обработчика (property_id = 107)
                $handlerType = $commands->propertyById(107)?->pivot->value;
                
                // Получаем значение обработчика (property_id = 108)
                $handlerValue = $commands->propertyById(108)?->pivot->value;

                if ($handlerType === 'text' && $handlerValue) {
                    $this->answer = $handlerValue;
                } elseif ($handlerType === 'handler' && $handlerValue) {
                    // Парсим строку формата "ClassName@method"
                    $handlerParts = explode('@', $handlerValue);
                    
                    if (count($handlerParts) === 2) {
                        $handlerClass = $handlerParts[0];
                        $handlerMethod = $handlerParts[1];
                        
                        if (class_exists($handlerClass) && method_exists($handlerClass, $handlerMethod)) {
                            $handler = new $handlerClass;
                            $this->answer = $handler->$handlerMethod($text, $commands);
                        } else {
                            Log::error("Handler class or method not found: " . $handlerValue);
                            $this->answer = "Ошибка обработки команды: обработчик не найден";
                        }
                    } else {
                        Log::error("Invalid handler format: " . $handlerValue);
                        $this->answer = "Неверный формат обработчика. Используйте: ClassName@method";
                    }
                }

                // Увеличиваем счетчик использования
                $counter = $commands->propertyById('counter');
                if ($counter) {
                    $counter->pivot->value = (int)$counter->pivot->value + 1;
                    $counter->pivot->save();
                } else {
                    $commands->propertys()->attach('counter', ['value' => 1]);
                }

                Log::info("Command executed: " . $commands->id);
            } else {
                // Логируем не найденную команду
                Log::info("Command not found for text: " . $text);
            }

        } catch (\Exception $e) {
            Log::error("Error executing command: " . $e->getMessage());
            $this->answer = "Произошла ошибка при обработке команды";
        }

        return $this->answer;
    }

    // Удаление команды
    public function destroy($id)
    {
        try {
            $command = CommandsModel::find($id);
            if ($command) {
                $command->propertys()->detach();
                $command->delete();
                
                // Запускаем генерацию списка команд после удаления
                dispatch( new GenerateCommandsList());

                
                return response()->json(['success' => true]);
            }
            
            return response()->json(['success' => false, 'message' => 'Команда не найдена'], 404);
            
        } catch (\Exception $e) {
            Log::error("Error deleting command: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Ошибка при удалении'], 500);
        }
    }

    // Тестирование команды
    public function testCommand(Request $request, $id)
    {
        $command = CommandsModel::find($id);
        $testText = $request->input('text', '');
        
        if ($command && $testText) {
            $result = $this->getAnswer($testText);
            return response()->json(['success' => true, 'result' => $result]);
        }
        
        return response()->json(['success' => false, 'message' => 'Неверные параметры теста']);
    }
}