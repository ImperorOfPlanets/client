<?php
namespace App\Http\Controllers\Management\Settings\Pwa;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Settings\Site\SettingsModel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;


class PwaController extends Controller
{
    private $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    //Основные переменные
    public $basicPWA = [
        'name' => 'Your App Name',
        'short_name' => 'App',
        'start_url' => '/',
        'display' => 'standalone',
        'background_color' => '#ffffff',
        'theme_color' => '#000000'
    ];

    //Рамезры для генерации
    public $sizes =
    [
        [
            'description' => 'Адаптивные иконки для различных устройств и платформ.',
            'icons' =>
            [
                [
                    'id' => 'icon192',
                    'width' => 192,
                    'height' => 192,
                    'description' => 'Стандартный размер для PWA, поддерживаемый большинством браузеров.'
                ],
                [
                    'id' => 'icon256',
                    'width' => 256,
                    'height' => 256,
                    'description' => 'Иногда используется для средних устройств.'
                ],
                [
                    'id' => 'icon384',
                    'width' => 384,
                    'height' => 384,
                    'description' => 'Для устройств с высоким разрешением (HiDPI).'
                ],
                [
                    'id' => 'icon512',
                    'width' => 512,
                    'height' => 512,
                    'description' => 'Обязательный размер для Google Play и часто рекомендуемый для Android.'
                ],
                [
                    'id' => 'icon1024',
                    'width' => 1024,
                    'height' => 1024,
                    'description' => 'Рекомендуется для иконок с высокой детализацией в некоторых случаях.'
                ]
            ]
        ],
        [
            'description' => 'iOS-специфические размеры иконок для различных устройств Apple.',
            'icons' =>
            [
                [
                    'id' => 'icon57',
                    'width' => 57,
                    'height' => 57,
                    'description' => 'Стандартная иконка для устройств iPhone.'
                ],
                [
                    'id' => 'icon60',
                    'width' => 60,
                    'height' => 60,
                    'description' => 'Для iPhone с Retina-дисплеем.'
                ],
                [
                    'id' => 'icon72',
                    'width' => 72,
                    'height' => 72,
                    'description' => 'Для iPad'
                ],
                [
                    'id' => 'icon76',
                    'width' => 76,
                    'height' => 76,
                    'description' => 'Для iPad с Retina-дисплеем.'
                ],
                [
                    'id' => 'icon114',
                    'width' => 114,
                    'height' => 114,
                    'description' => 'Для iPhone Retina.'
                ],
                [
                    'id' => 'icon120',
                    'width' => 120,
                    'height' => 120,
                    'description' => 'Для iPhone Retina HD (iPhone 6/7/8).'
                ],
                [
                    'id' => 'icon144',
                    'width' => 144,
                    'height' => 144,
                    'description' => 'Для iPad Retina HD.'
                ],
                [
                    'id' => 'icon152',
                    'width' => 152,
                    'height' => 152,
                    'description' => 'Для iPad Pro.'
                ],
                [
                    'id' => 'icon180',
                    'width' => 180,
                    'height' => 180,
                    'description' => 'Иконка для современных iPhone с Retina-дисплеями.'
                ]
            ]
        ],
        [
            'description' => 'Favicon для браузеров.',
            'icons' =>
            [
                [
                    'id' => 'favicon16',
                    'width' => 16,
                    'height' => 16,
                    'description' => 'Используется в адресной строке браузеров.'
                ],
                [
                    'id' => 'favicon32',
                    'width' => 32,
                    'height' => 32,
                    'description' => 'Для панелей задач и закладок браузеров.'
                ],
                [
                    'id' => 'favicon48',
                    'width' => 48,
                    'height' => 48,
                    'description' => 'Для увеличенных иконок браузеров.'
                ]
            ]
        ],
        [
            'description' => 'Splash Screen (Загрузочный экран) Для Android и iOS требуются изображения с различными размерами.',
            'icons' =>
            [
                [
                    'id' => 'splash320x480',
                    'width' => 320,
                    'height' => 480,
                    'description' => 'Для iPhone (портрет)'
                ],
                [
                    'id' => 'splash640x960',
                    'width' => 640,
                    'height' => 960,
                    'description' => 'Для iPhone Retina (портрет)'
                ],
                [
                    'id' => 'splash750x1334',
                    'width' => 750,
                    'height' => 1334,
                    'description' => 'Для iPhone 6/7/8 (портрет)'
                ],
                [
                    'id' => 'splash1125x2436',
                    'width' => 1125,
                    'height' => 2436,
                    'description' => 'Для iPhone X/XS/11 Pro (портрет)'
                ],
                [
                    'id' => 'splash1242x2688',
                    'width' => 1242,
                    'height' => 2688,
                    'description' => 'Для iPhone XS Max/11 Pro Max (портрет)'
                ],
                [
                    'id' => 'splash828x1792',
                    'width' => 828,
                    'height' => 1792,
                    'description' => 'Для iPhone XR/11 (портрет)'
                ],
                [
                    'id' => 'splash1242x2208',
                    'width' => 1242,
                    'height' => 2208,
                    'description' => 'Для iPhone 6+/7+/8+ (портрет)'
                ],
                [
                    'id' => 'splash1536x2048',
                    'width' => 1536,
                    'height' => 2048,
                    'description' => 'Для iPad (портрет)'
                ],
                [
                    'id' => 'splash1668x2224',
                    'width' => 1668,
                    'height' => 2224,
                    'description' => 'Для iPad Pro 10.5" (портрет)'
                ],
                [
                    'id' => 'splash1668x2388',
                    'width' => 1668,
                    'height' => 2388,
                    'description' => 'Для iPad Pro 11" (портрет)'
                ],
                [
                    'id' => 'splash2048x2732',
                    'width' => 2048,
                    'height' => 2732,
                    'description' => 'Для iPad Pro 12.9" (портрет)'
                ]
            ]
        ],
        [
            'description' => 'Значок для панели задач Windows.',
            'icons' =>
            [
                [
                    'id' => 'tile70x70',
                    'width' => 70,
                    'height' => 70,
                    'description' => 'Маленькая плитка Windows.'
                ],
                [
                    'id' => 'tile150x150',
                    'width' => 150,
                    'height' => 150,
                    'description' => 'Средняя плитка Windows.'
                ],
                [
                    'id' => 'tile310x150',
                    'width' => 310,
                    'height' => 150,
                    'description' => 'Широкая плитка Windows.'
                ],
                [
                    'id' => 'tile310x310',
                    'width' => 310,
                    'height' => 310,
                    'description' => 'Большая плитка Windows.'
                ]
            ]
        ],
        [
            'description' => 'Android Adaptive Icons',
            'icons' =>
            [
                [
                    'id' => 'adaptive108dp',
                    'width' => 108,
                    'height' => 108,
                    'description' => 'Адаптивная иконка для Android 8.0+ (xxxhdpi)'
                ]
            ]
        ],
        [
            'description' => 'MacOS Touch Bar Icon',
            'icons' =>
            [
                [
                    'id' => 'touchbar44',
                    'width' => 44,
                    'height' => 44,
                    'description' => 'Иконка для Touch Bar на MacOS'
                ]
            ]
        ],
        [
            'description' => 'Open Graph и Social Sharing',
            'icons' =>
            [
                [
                    'id' => 'og1200x630',
                    'width' => 1200,
                    'height' => 630,
                    'description' => 'Для отображения в соцсетях (Facebook, Twitter)'
                ],
                [
                    'id' => 'social1024',
                    'width' => 1024,
                    'height' => 1024,
                    'description' => 'Общий размер для социальных сетей'
                ]
            ]
        ]
    ];

    /**
     * Выводит страницу с настройка для генерации
     */
    public function index(Request $request)
    {
        // Получаем объект с ID = 110
        $pwaSettings = SettingsModel::find(110);

        // Загружаем настройки PWA
        $imagesProperty = $pwaSettings->propertyById(102); // Настройки изображений

        // Декодируем JSON
        $imagesSettingsJson = json_decode($imagesProperty->pivot->value, true) ?? [];

        // Путь к директории
        $pwaDirectory = storage_path('pwa');
        
        // Переменная для пути к изображению
        $forAllImageUrl = null;

        // Получаем все файлы в директории pwa
        $files = File::files($pwaDirectory);

        // Ищем файл с названием "forAll" и любым расширением
        foreach ($files as $file) {
            if (basename($file, '.' . $file->getExtension()) === 'forAll') {
                // Генерируем URL к найденному файлу
                $forAllImageUrl = asset('storage/pwa/' . basename($file));
                break; // Останавливаем цикл, как только нашли нужный файл
            }
        }

        return view('management.settings.pwa.index',[
            'imagesSettingsJson'=>$imagesSettingsJson,
            'sizes'=>$this->sizes
        ]);
    }

    public function store(Request $request)
    {
        //Для генерации
        if($request->command == 'generate')
        {
            $pwaSettings = SettingsModel::find(110);
            $imagesProperty = $pwaSettings->propertyById(102);
        
            // Декодируем существующий JSON в массив
            $json = json_decode($imagesProperty->pivot->value, true) ?? [];
            // Убедимся, что необходимый формат данных присутствует
            if (!isset($json['name'], $json['short_name'], $json['start_url'], $json['display'], $json['background_color'], $json['theme_color'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Некорректный формат данных в базе данных.',
                ], 400);
            }
        
            // Генерация manifest.json из данных $json
            $manifest = [
                'name' => $json['name'],
                'short_name' => $json['short_name'],
                'start_url' => $json['start_url'],
                'display' => $json['display'],
                'background_color' => $json['background_color'],
                'theme_color' => $json['theme_color'],
                'icons' => [],
            ];

            // Добавление иконок в manifest.json
            foreach ( $this->sizes as $category)
            {
                //Если есть массив иконок
                if(isset($category['icons']))
                {
                    //Перебираем иконки
                    foreach($category['icons'] as $icon)
                    {
                        if (isset($icon['id'], $icon['width'], $icon['height']))
                        {
                            // Формируем абсолютный путь к файлу
                            $filePath = public_path("img/pwa/icons/{$icon['id']}.png");

                            // Проверяем наличие файла
                            if (file_exists($filePath)) {
                                $manifest['icons'][] = [
                                    'src' => "/img/pwa/icons/{$icon['id']}.png", // путь к иконке
                                    'sizes' => "{$icon['width']}x{$icon['height']}",
                                    'type' => 'image/png',
                                ];
                            }
                        }
                    }
                }
            }

            // Формируем путь для сохранения manifest.json
            $manifestPath = public_path('manifest.json');

            // Сохраняем JSON в файл
            file_put_contents($manifestPath, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        
            // Ответ успешной генерации
            return response()->json([
                'success' => true,
                'message' => 'Manifest.json успешно сгенерирован.',
            ]);
        }
        //Для изменения значения настроек
        elseif($request->command=='change-value')
        {
            $pwaSettings = SettingsModel::find(110);
            $imagesProperty = $pwaSettings->propertyById(102);
            
            // Декодируем существующий JSON в массив
            $json = json_decode($imagesProperty->pivot->value, true) ?? [];

            // Сравниваем с `$basicPWA` для удаления лишних ключей
            $validKeys = array_keys($this->basicPWA);
            foreach ($json as $key => $value)
            {
                if (!in_array($key, $validKeys)) {
                    unset($json[$key]); // Удаляем ключ, если он отсутствует в `$basicPWA`
                }
            }

            // Добавляем недостающие базовые значения
            foreach ($this->basicPWA as $key => $defaultValue)
            {
                if (!isset($json[$key])) {
                    $json[$key] = $defaultValue;
                }
            }

            // Обновляем или добавляем новое значение
            $json[$request->key] = $request->value;
            
            // Кодируем массив обратно в JSON
            $imagesProperty->pivot->value = json_encode($json, JSON_UNESCAPED_UNICODE);
            
            // Сохраняем изменения
            $imagesProperty->pivot->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Настройки обновлены.',
            ]);
            
        }
        elseif($request->command=='upload')
        {
            $request->validate([
                'file' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
                'iconId' => 'required|string', // Уникальный идентификатор изображения
            ]);
        
            $iconId = $request->iconId;
            $originalPath = "public/originals/{$iconId}.png";
            $processedPath = "public/icons/{$iconId}.png";
        
            // Сохраняем оригинальное изображение
            $file = $request->file('file');
            Storage::put($originalPath, file_get_contents($file));
        
            // Создаем копию для дальнейшего использования
            $image = $this->imageManager->read($file)->toPng();
            Storage::put($processedPath, (string)$image);
        
            return response()->json([
                'success' => true,
                'message' => 'Файл загружен и обработан.',
            ]);
        }
        elseif($request->command=='crop')
        {
            $request->validate([
                'iconId' => 'required|string', // Уникальный идентификатор изображения
                'x' => 'required|numeric', // Координата X
                'y' => 'required|numeric', // Координата Y
                'width' => 'required|numeric', // Ширина
                'height' => 'required|numeric', // Высота
            ]);
    
            $iconId = $request->iconId;
            $originalPath = storage_path("app/public/originals/{$iconId}.png");
            $processedPath = "public/icons/{$iconId}.png";
    
            if (!file_exists($originalPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Оригинальное изображение не найдено.',
                ]);
            }
    
            // Загружаем изображение и обрезаем
            $image = $this->imageManager->read($originalPath)
                ->crop(
                    (int)$request->width,
                    (int)$request->height,
                    (int)$request->x,
                    (int)$request->y
                )
                ->toPng();
    
            // Сохраняем обрезанное изображение
            Storage::put($processedPath, (string)$image);
    
            return response()->json([
                'success' => true,
                'message' => 'Изображение успешно обрезано.',
            ]);
        }
        elseif($request->command=='uploadForAll')
        {
            \Log::info("=== UPLOAD FOR ALL DEBUG START ===");
            
            $request->validate([
                'file' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            // Получаем файл
            $file = $request->file('file');
            \Log::info("File uploaded: " . $file->getClientOriginalName());
            \Log::info("File size: " . $file->getSize());
            \Log::info("File MIME: " . $file->getMimeType());

            // Определяем расширение файла
            $extension = $file->getClientOriginalExtension();
            \Log::info("File extension: " . $extension);

            // Формируем имя файла
            $filename = 'forAll.' . $extension;

            // Путь к директории
            $forAllDirectory = public_path('/img/pwa');
            \Log::info("Target directory: " . $forAllDirectory);

            // Получаем все файлы в директории
            $files = File::files($forAllDirectory);
            \Log::info("Existing files before cleanup: " . json_encode(array_map(function($f) {
                return $f->getFilename();
            }, $files)));

            // Удаляем все файлы с названием "forAll" (независимо от расширения)
            foreach ($files as $file) {
                if (basename($file, '.' . $file->getExtension()) === 'forAll') {
                    \Log::info("Deleting old file: " . $file->getFilename());
                    File::delete($file); // Удаляем файл
                }
            }

            // Создаем папку, если она не существует
            if (!file_exists($forAllDirectory)) {
                \Log::info("Creating directory: " . $forAllDirectory);
                mkdir($forAllDirectory, 0777, true);
            }

            // Сохраняем файл в папке pwa с именем forAll и нужным расширением
            $targetPath = $forAllDirectory . '/' . $filename;
            $file->move($forAllDirectory, $filename);
            
            \Log::info("File saved to: " . $targetPath);
            \Log::info("File exists after save: " . (file_exists($targetPath) ? 'YES' : 'NO'));
            \Log::info("=== UPLOAD FOR ALL DEBUG END ===");

            return response()->json([
                'success' => true,
                'message' => 'Файл загружен и обработан.',
            ]);
        }
        elseif($request->command=='resizeAll')
        {
            \Log::info("=== RESIZE ALL DEBUG START ===");
            
            // Пути к директориям
            $iconsDirectory = public_path('/img/pwa/icons');
            $forAllDirectory = public_path('/img/pwa');
            $forALL = false;

            \Log::info("Searching forALL file in: " . $forAllDirectory);
            
            //Получаем файл для всех
            $files = File::allFiles($forAllDirectory);
            \Log::info("Files found in directory: " . json_encode(array_map(function($file) {
                return $file->getFilename();
            }, $files)));

            foreach ($files as $file) {
                $filenameWithoutExtension = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                \Log::info("Checking file: " . $file->getFilename() . " -> " . $filenameWithoutExtension);
                
                if ($filenameWithoutExtension === 'forAll') {
                    $extension = $file->getExtension();
                    $forALL = public_path('/img/pwa/forAll.'.$extension);
                    \Log::info("Found forALL file: " . $forALL);
                    break;
                }
            }
            
            if($forALL == false)
            {
                \Log::error("No forALL file found!");
                return response()->json([
                    'success' => false,
                    'message' => 'Файл для всех не загружен.',
                ]);
            }

            \Log::info("Using source file: " . $forALL);
            \Log::info("Source file exists: " . (file_exists($forALL) ? 'YES' : 'NO'));
            \Log::info("Source file size: " . (file_exists($forALL) ? filesize($forALL) : '0'));

            // Директория для сохранения иконок
            $iconsDirectory = public_path('/img/pwa/icons');
            \Log::info("Icons directory: " . $iconsDirectory);
            \Log::info("Icons directory exists: " . (file_exists($iconsDirectory) ? 'YES' : 'NO'));
            
            if (!file_exists($iconsDirectory)) {
                \Log::info("Creating icons directory");
                mkdir($iconsDirectory, 0777, true);
            }

            $processedIcons = 0;
            
            //Перебираем категории с иконками
            foreach($this->sizes as $category)
            {
                //Если есть массив иконок
                if(isset($category['icons']))
                {
                    //Перебираем иконки
                    foreach($category['icons'] as $icon)
                    {
                        $iconId = $icon['id'];
                        $iconWidth = $icon['width'];
                        $iconHeight = $icon['height'];

                        //Проверяем наличие ширины и высоты
                        if(isset($icon['width']) && isset($icon['height']))
                        {
                            //Проверряем соотношение
                            if($icon['width'] == $icon['height'])
                            {
                                // Формируем путь для сохранения
                                $iconPath = $iconsDirectory . '/' . $iconId . '.png';
                                
                                \Log::info("Processing icon: " . $iconId);
                                \Log::info("Target path: " . $iconPath);
                                \Log::info("Dimensions: " . $iconWidth . "x" . $iconHeight);

                                try {
                                    // Используем ImageManager для обработки изображения
                                    $image = $this->imageManager->read($forALL);
                                    \Log::info("Image loaded successfully");
                                    
                                    // Создаем квадратное изображение
                                    $image->cover($iconWidth, $iconHeight)->save($iconPath);
                                    \Log::info("Icon saved: " . $iconPath);
                                    \Log::info("File exists after save: " . (file_exists($iconPath) ? 'YES' : 'NO'));
                                    
                                    $processedIcons++;
                                    
                                } catch (\Exception $e) {
                                    \Log::error("Error processing icon " . $iconId . ": " . $e->getMessage());
                                }
                            }
                        }
                    }
                }
            }

            \Log::info("=== RESIZE ALL DEBUG END ===");
            \Log::info("Total icons processed: " . $processedIcons);

            return response()->json([
                'success' => true,
                'message' => 'Все иконки успешно сгенерированы. Обработано: ' . $processedIcons,
            ]);
        }
    }
}