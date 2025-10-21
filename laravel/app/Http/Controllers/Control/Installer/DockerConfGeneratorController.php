<?php

namespace App\Http\Controllers\Control\Installer;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Http;


class DockerConfGeneratorController extends Controller{

    //Переменные с .env.example
    public $exampleEnvVariables = [];

    //Переменные к которым есть название, описание и функции получения данных с БД
    public $formVariables = [
        [
            'title' => 'PROJECT VARIABLES',
            'description' => 'Основные настройки проекта',
            'fields' => [
                [
                    'name' => 'PROJECTNAME',
                    'label' => 'Имя проекта',
                    'type' => 'text',
                    'required' => true,
                    'function' => 'getProjectName'
                ],
                [
                    'name' => 'COMPOSE_PROJECT_NAME',
                    'label' => 'Имя окружения Docker',
                    'type' => 'text',
                    'function' => 'getComposeProjectName'
                ],
                [
                    'name' => 'TYPE_SERVER',
                    'label' => 'Тип сервера (CLIENT | AI | VOICE) - НЕ РАБОТАЕТ',
                    'type' => 'select',
                    'options' => ['CLIENT', 'AI', 'VOICE'],
                    'disabled' => true
                ],
                [
                    'name' => 'ENABLED_SERVICES',
                    'label' => 'Дополнительно включаемые сервисы',
                    'type' => 'multiselect',
                    'options' => ['MARIADB', 'REVERB', 'WEBSSH', 'WEBSSHCLIENT', 'REDIS']
                ],
                [
                    'name' => 'DOMAIN',
                    'label' => 'Основной домен приложения',
                    'type' => 'text',
                    'required' => true
                ]
            ]
        ],
        [
            'title' => 'VPN SETTINGS',
            'description' => 'Настройки VPN подключения',
            'fields' => [
                [
                    'name' => 'VPN_REQUIRED',
                    'label' => 'Необходимость подключения VPN',
                    'type' => 'select',
                    'options' => ['required', 'optional', 'disabled']
                ],
                [
                    'name' => 'VPN_CONFIG',
                    'label' => 'Конфигурационный файл OpenVPN',
                    'type' => 'file',
                    'dependency' => 'VPN_REQUIRED != "disabled"'
                ]
            ]
        ],
        [
            'title' => 'DATABASE SETTINGS',
            'description' => 'Настройки базы данных',
            'fields' => [
                [
                    'name' => 'DB_DATA_PATH',
                    'label' => 'Путь к данным БД',
                    'type' => 'text',
                    'function' => 'getDbPath'
                ],
                [
                    'name' => 'DB_CONNECTION',
                    'label' => 'Тип базы данных',
                    'type' => 'select',
                    'options' => ['mariadb', 'mysql', 'pgsql', 'sqlite']
                ],
                [
                    'name' => 'DB_HOST',
                    'label' => 'Адрес сервера БД',
                    'type' => 'text'
                ],
                [
                    'name' => 'DB_PORT',
                    'label' => 'Порт базы данных',
                    'type' => 'number'
                ],
                [
                    'name' => 'DB_DATABASE',
                    'label' => 'Имя базы данных',
                    'type' => 'text'
                ],
                [
                    'name' => 'DB_USERNAME',
                    'label' => 'Пользователь БД',
                    'type' => 'text'
                ],
                [
                    'name' => 'DB_PASSWORD',
                    'label' => 'Пароль пользователя БД',
                    'type' => 'password'
                ],
                [
                    'name' => 'DB_PASSWORD_ROOT',
                    'label' => 'Пароль администратора БД',
                    'type' => 'password'
                ]
            ]
        ],
        [
            'title' => 'LARAVEL APP SETTINGS',
            'description' => 'Настройки приложения Laravel',
            'fields' => [
                [
                    'name' => 'CLIENT_DATA_PATH',
                    'label' => 'Путь к проекту Laravel',
                    'type' => 'text',
                    'function' => 'getClientPath'
                ],
                [
                    'name' => 'APP_ENV',
                    'label' => 'Окружение приложения',
                    'type' => 'select',
                    'options' => ['production', 'staging', 'local']
                ],
                [
                    'name' => 'APP_DEBUG',
                    'label' => 'Режим отладки',
                    'type' => 'checkbox'
                ],
                [
                    'name' => 'APP_URL',
                    'label' => 'Базовый URL приложения',
                    'type' => 'text'
                ],
                [
                    'name' => 'CACHE_STORE',
                    'label' => 'Метод кеширования',
                    'type' => 'select',
                    'options' => ['file', 'database', 'redis']
                ],
                [
                    'name' => 'QUEUE_CONNECTION',
                    'label' => 'Драйвер очередей',
                    'type' => 'select',
                    'options' => ['sync', 'database', 'redis']
                ],
                [
                    'name' => 'OAUTH_REDIRECT_URI',
                    'label' => 'URL перенаправления OAuth',
                    'type' => 'hidden',
                    'function' => 'generateOAuthRedirectUri'
                ],
                [
                    'name' => 'OAUTH_SECRET',
                    'label' => 'Секрет OAuth',
                    'type' => 'text'
                ],
                [
                    'name' => 'OAUTH_CLIENT_ID',
                    'label' => 'ID клиента OAuth',
                    'type' => 'text'
                ]
            ]
        ]
    ];

    public function index()
    {
        $this->downloadAndParseEnvFile();
    }

    //Скачивает пример файла для докера
    public function downloadAndParseEnvFile()
    {
        try {
            // Скачаем файл по указанному URL
            $response = Http::get('http://gitflic.myidon.site/project/adminuser/docker/blob/raw?file=.env.example');

            if ($response->successful()) {
                // Содержимое файла
                $content = $response->body();

                // Теперь разобъем полученный контент на строки
                $lines = explode("\n", $content);

                // Переменные будут храниться тут
                $variables = [];    
                foreach ($lines as $line) {
                    // Удалим лишние пробелы
                    $line = trim($line);
                    // Пропускаем пустые строки и комментарии
                    if (!empty($line) && substr($line, 0, 1) != '#') {
                        // Найдем "=" в строке
                        $position = strpos($line, '=');
                        
                        if ($position !== false) {
                            // Извлекаем ключ и значение
                            $key = trim(substr($line, 0, $position));
                            $value = trim(substr($line, $position + 1));
                            
                            // Сохраняем переменную
                            $variables[$key] = $value;
                        }
                    }
                }
                $this->exampleVariables = $variables;
            } else {
                throw new \Exception('Ошибка загрузки файла.');
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Ошибка обработки файла: ' . $e->getMessage(),
            ], 500);
        }
    }
}