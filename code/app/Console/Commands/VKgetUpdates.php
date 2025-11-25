<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Socials\SocialsModel;
use App\Models\Socials\UpdatesModel;
use App\Models\Assistant\MessagesModel;
use VK\Client\VKApiClient;
use App\Helpers\Logs\Logs as Logator;

/**
 * CLASS: VKgetUpdates
 * PURPOSE: Команда для получения обновлений VK через long polling
 * DESCRIPTION: Обрабатывает входящие сообщения VK, сохраняет обновления в базу данных и управляет циклом long polling
 * CATEGORY: command (Laravel Artisan Command)
 * USES: [Laravel, VK API, VK PHP SDK, Eloquent ORM, Long Polling]
 */
class VKgetUpdates extends Command
{
    /**
     * SIGNATURE: command:VKgetUpdates
     * DESCRIPTION: Сигнатура команды Artisan для запуска через php artisan command:VKgetUpdates
     */
    protected $signature = 'command:VKgetUpdates';

    /**
     * DESCRIPTION: Run VK bot using long polling
     * USAGE: Обработчик VK бота через механизм long polling
     */
    protected $description = 'Run VK bot using long polling';

    /**
     * PROPERTY: vk
     * TYPE: VKApiClient
     * PURPOSE: Клиент для взаимодействия с VK API версии 5.131
     */
    private $vk;

    /**
     * PROPERTY: settings
     * TYPE: SocialsModel
     * PURPOSE: Настройки социальной сети VK из базы данных (ID: 13)
     */
    private $settings;

    /**
     * PROPERTY: logator
     * TYPE: Logator
     * PURPOSE: Логгер для записи событий и ошибок с идентификатором процесса
     */
    private $logator;

    /**
     * PROPERTY: serverParams
     * TYPE: array
     * PURPOSE: Параметры long polling сервера VK (server, key, ts)
     * STRUCTURE: ['server' => string, 'key' => string, 'ts' => string]
     */
    private $serverParams = [
        'server' => null,
        'key' => null,
        'ts' => null
    ];

    // CONFIG: Константы для настройки поведения long polling
    private const WAIT_TIME = 25; // Время ожидания long polling (сек)
    private const ERROR_DELAY = 5; // Задержка при ошибках (сек)
    private const MAX_RETRIES = 3; // Максимальное количество попыток повторения

    /**
     * METHOD: __construct
     * PURPOSE: Инициализация команды, логгера и VK клиента
     * PROCESS: 
     * 1. Вызов родительского конструктора
     * 2. Создание экземпляра логгера с идентификатором процесса
     * 3. Инициализация VK API клиента версии 5.131
     */
    public function __construct()
    {
        parent::__construct();
        $this->logator = new Logator;
        $this->logator->setAuthor('VKgetUpdates - ' . getmypid());
        $this->vk = new VKApiClient('5.131');
    }

    /**
     * METHOD: handle
     * PURPOSE: Основной метод выполнения команды
     * PROCESS:
     * 1. Логирование запуска обработчика и PID процесса
     * 2. Бесконечный цикл работы команды
     * 3. Получение настроек VK из базы данных
     * 4. Проверка статуса активации VK
     * 5. Инициализация параметров long polling сервера
     * 6. Запуск основного цикла получения обновлений
     * ERROR_HANDLING: Перехват исключений с задержкой перед повторной попыткой
     */
    public function handle()
    {
        // LOG: Запись информации о запуске обработчика
        $this->logator->setType('info')->setText('Запуск обработчика VK')->write();
        $this->logator->setType('info')->setText('PID процесса: ' . getmypid())->write();

        // PROCESS: Основной бесконечный цикл работы команды
        while (true) {
            try {
                // MONITORING: Логирование использования памяти
                $this->logMemoryUsage('Начало цикла');
                
                // DATA: Получение настроек социальной сети VK (ID: 13)
                $this->settings = $this->getSettings();
                if (!$this->settings) {
                    sleep(self::ERROR_DELAY);
                    continue;
                }

                // CHECK: Проверка активации VK в настройках системы
                if (!$this->checkVKStatus()) {
                    sleep(self::ERROR_DELAY);
                    continue;
                }

                // CONFIG: Инициализация параметров long polling сервера
                $this->initializeServerParams();

                // PROCESS: Запуск основного цикла получения обновлений
                $this->getUpdatesLoop();

            } catch (\Exception $e) {
                // ERROR: Обработка критических ошибок с задержкой
                $this->handleError($e);
                sleep(self::ERROR_DELAY);
            }
        }
    }

    /**
     * METHOD: getSettings
     * PURPOSE: Получение настроек социальной сети VK из базы данных
     * PROCESS:
     * 1. Поиск настроек по ID 13
     * 2. Проверка наличия настроек
     * 3. Логирование ошибок при отсутствии настроек
     * RETURNS: SocialsModel|null - модель настроек или null при ошибке
     */
    private function getSettings()
    {
        try {
            // DATABASE: Поиск настроек VK в таблице SocialsModel
            $settings = SocialsModel::find(13);
            
            // VALIDATION: Проверка существования настроек
            if (!$settings) {
                $this->logator->setType('error')
                    ->setText('Настройки VK не найдены в БД (ID 13)')
                    ->write();
                return null;
            }
            
            return $settings;
            
        } catch (\Exception $e) {
            // ERROR: Логирование ошибок доступа к базе данных
            $this->logator->setType('error')
                ->setText('Ошибка получения настроек: ' . $e->getMessage())
                ->write();
            return null;
        }
    }

    /**
     * METHOD: logMemoryUsage
     * PURPOSE: Логирование использования памяти для мониторинга производительности
     * PROCESS:
     * 1. Получение текущего использования памяти
     * 2. Конвертация в мегабайты
     * 3. Запись в лог с контекстом
     * PARAMETERS: string $context - контекст для идентификации в логах
     */
    private function logMemoryUsage(string $context = '')
    {
        $memory = memory_get_usage(true);
        $memoryMb = round($memory / 1024 / 1024, 2);
        $this->logator->setType('debug')
            ->setText("{$context} - Использование памяти: {$memoryMb} MB")
            ->write();
    }

    /**
     * METHOD: getUpdatesLoop
     * PURPOSE: Основной цикл получения обновлений через long polling
     * PROCESS:
     * 1. Подсчет количества попыток для ограничения повторений
     * 2. Запрос обновлений от VK API
     * 3. Сброс счетчика попыток при успешном запросе
     * 4. Обработка полученных обновлений
     * 5. Обработка ошибок с повторными попытками
     * ERROR_STRATEGY: Превышение MAX_RETRIES приводит к перезапуску цикла
     */
    private function getUpdatesLoop()
    {
        $retryCount = 0;
        
        // PROCESS: Внутренний цикл непрерывного получения обновлений
        while (true) {
            try {
                $this->logator->setType('debug')->setText('Запрос обновлений...')->write();
                
                // API: Запрос обновлений через long polling
                $response = $this->getUpdates();
                
                // RESET: Сброс счетчика попыток при успешном запросе
                $retryCount = 0;
                
                // PROCESS: Пропуск итерации при null ответе (обработанные ошибки)
                if ($response === null) {
                    continue;
                }
                
                // PROCESS: Обработка успешно полученных обновлений
                $this->processUpdates($response);
                
            } catch (\Exception $e) {
                // ERROR: Увеличение счетчика ошибок и проверка лимита
                $retryCount++;
                
                if ($retryCount >= self::MAX_RETRIES) {
                    $this->logator->setType('error')
                        ->setText('Достигнуто максимальное количество попыток. Перезапуск цикла.')
                        ->write();
                    throw $e;
                }
                
                $this->handleError($e);
                sleep(self::ERROR_DELAY);
            }
        }
    }

    /**
     * METHOD: checkVKStatus
     * PURPOSE: Проверка активности VK в настройках системы
     * PROCESS:
     * 1. Получение значения свойства 116 (статус активации)
     * 2. Проверка наличия и значения статуса
     * 3. Логирование предупреждения при отключенном статусе
     * RETURNS: boolean - true если VK активен, false если отключен
     */
    private function checkVKStatus(): bool
    {
        // DATA: Получение значения свойства активации через связующую таблицу
        $status = $this->settings->propertyById(116)->pivot->value ?? null;
        
        // VALIDATION: Проверка активации VK в настройках
        if (!$status) {
            $this->logator->setType('warning')
                ->setText('VK отключен в настройках. Ожидание включения...')
                ->write();
            return false;
        }

        return true;
    }

    /**
     * METHOD: initializeServerParams
     * PURPOSE: Инициализация параметров long polling сервера
     * PROCESS:
     * 1. Загрузка параметров из базы данных (server, key, ts)
     * 2. Логирование текущих параметров
     * 3. Получение новых параметров при отсутствующих значениях
     * CONFIG_IDS: 
     * - 46: server URL
     * - 47: key
     * - 48: ts (timestamp)
     */
    private function initializeServerParams()
    {
        // DATA: Загрузка параметров сервера из базы данных
        $this->serverParams = [
            'server' => $this->settings->propertyById(46)->pivot->value ?? null,
            'key' => $this->settings->propertyById(47)->pivot->value ?? null,
            'ts' => $this->settings->propertyById(48)->pivot->value ?? null
        ];

        $this->logator->setType('debug')
            ->setText('Текущие параметры сервера: ' . json_encode($this->serverParams))
            ->write();

        // PROCESS: Получение новых параметров при неполных данных
        if (in_array(null, $this->serverParams, true)) {
            $this->getNewServerParams();
        }
    }

    /**
     * METHOD: getNewServerParams
     * PURPOSE: Получение новых параметров long polling сервера от VK API
     * PROCESS:
     * 1. Проверка наличия access_token и group_id в настройках
     * 2. Запрос к VK API groups.getLongPollServer
     * 3. Сохранение новых параметров в базу данных
     * 4. Обновление локальных параметров сервера
     * CONFIG_IDS:
     * - 45: access_token
     * - 40: group_id
     * THROWS: \Exception при отсутствии обязательных параметров или ошибках API
     */
    private function getNewServerParams()
    {
        // CONFIG: Получение access_token и group_id из настроек
        $access_token = $this->settings->propertyById(45)->pivot->value ?? null;
        $group_id = $this->settings->propertyById(40)->pivot->value ?? null;

        // VALIDATION: Проверка обязательных параметров
        if (empty($access_token)) {
            $this->logator->setType('error')
                ->setText('Не указан access_token в настройках VK! Параметр 45')
                ->write();
            throw new \Exception('Access token not set');
        }

        if (empty($group_id)) {
            $this->logator->setType('error')
                ->setText('Не указан group_id в настройках VK! Параметр 40')
                ->write();
            throw new \Exception('Group ID not set');
        }

        try {
            // API: Запрос параметров long polling сервера от VK
            $data = $this->vk->groups()->getLongPollServer($access_token, [
                'group_id' => $group_id
            ]);

            // UPDATE: Сохранение полученных параметров
            $this->serverParams = [
                'server' => $data['server'],
                'key' => $data['key'],
                'ts' => $data['ts']
            ];

            // DATABASE: Сохранение новых параметров в базу данных
            $this->settings->propertys()->updateExistingPivot(46, ['value' => $data['server']]);
            $this->settings->propertys()->updateExistingPivot(47, ['value' => $data['key']]);
            $this->settings->propertys()->updateExistingPivot(48, ['value' => $data['ts']]);

            $this->logator->setType('success')
                ->setText('Параметры сервера успешно обновлены')
                ->write();

        } catch (\Exception $e) {
            // ERROR: Логирование ошибок получения параметров сервера
            $this->logator->setType('error')
                ->setText('Ошибка получения параметров сервера: ' . $e->getMessage())
                ->write();
            throw $e;
        }
    }

    /**
     * METHOD: getUpdates
     * PURPOSE: Выполнение long polling запроса для получения обновлений
     * PROCESS:
     * 1. Формирование параметров запроса
     * 2. Создание HTTP контекста с таймаутом
     * 3. Выполнение запроса к long polling серверу
     * 4. Декодирование JSON ответа
     * 5. Обработка failed ответов
     * RETURNS: array|null - данные обновлений или null при обработанных ошибках
     * THROWS: \Exception при ошибках HTTP или JSON декодирования
     */
    private function getUpdates()
    {
        // PARAMS: Формирование параметров long polling запроса
        $params = [
            "act" => 'a_check',
            "key" => $this->serverParams['key'],
            "ts" => $this->serverParams['ts'],
            "wait" => self::WAIT_TIME,
            "mode" => 2, // Получение расширенных событий
            "version" => 3 // Версия Long Poll API
        ];

        $url = $this->serverParams['server'] . '?' . http_build_query($params, '', '&');
        
        // HTTP: Создание контекста с увеличенным таймаутом
        $context = stream_context_create([
            'http' => [
                'timeout' => self::WAIT_TIME + 5 // Таймаут больше чем wait
            ]
        ]);

        // API: Выполнение HTTP запроса с подавлением предупреждений
        $response = @file_get_contents($url, false, $context);
        
        // ERROR: Обработка ошибок HTTP запроса
        if ($response === false) {
            $error = error_get_last();
            throw new \Exception("Ошибка HTTP запроса: " . ($error['message'] ?? 'Неизвестная ошибка'));
        }

        // PARSE: Декодирование JSON ответа
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Ошибка декодирования JSON: " . json_last_error_msg());
        }

        // PROCESS: Обработка failed ответов VK API
        if (isset($data['failed'])) {
            $this->handleFailedResponse($data);
            return null;
        }

        return $data;
    }

    /**
     * METHOD: processUpdates
     * PURPOSE: Обработка полученных обновлений от VK API
     * PROCESS:
     * 1. Обновление ts для следующего запроса
     * 2. Сохранение ts в базу данных
     * 3. Логирование количества обновлений
     * 4. Обработка каждого обновления в отдельном блоке try-catch
     * 5. Сохранение обновлений в базу данных
     * 6. Специальная обработка новых сообщений
     * PARAMETERS: array $data - данные ответа от long polling сервера
     */
    private function processUpdates(array $data)
    {
        // UPDATE: Обновление timestamp для следующего запроса
        if (isset($data['ts'])) {
            $this->serverParams['ts'] = $data['ts'];
            $this->settings->propertys()->updateExistingPivot(48, ['value' => $data['ts']]);
        }

        // CHECK: Пропуск при отсутствии обновлений
        if (empty($data['updates'])) {
            return;
        }

        // LOG: Статистика полученных обновлений
        $count = count($data['updates']);
        $this->logator->setType('info')
            ->setText("Получено обновлений: $count")
            ->write();

        // PROCESS: Обработка каждого обновления с индивидуальной обработкой ошибок
        foreach ($data['updates'] as $update) {
            try {
                $this->saveUpdate($update);
                
                // SPECIAL: Дополнительная обработка новых сообщений
                if ($update['type'] === 'message_new') {
                    $this->processNewMessage($update);
                }
            } catch (\Exception $e) {
                // ERROR: Логирование ошибок обработки отдельных обновлений
                $this->logator->setType('error')
                    ->setText('Ошибка обработки обновления: ' . $e->getMessage())
                    ->write();
            }
        }
    }

    /**
     * METHOD: saveUpdate
     * PURPOSE: Сохранение обновления в базу данных
     * PROCESS:
     * 1. Создание записи в таблице UpdatesModel
     * 2. Указание социальной сети VK (ID: 13)
     * 3. Сохранение RAW данных обновления в JSON формате
     * PARAMETERS: array $update - данные обновления от VK API
     */
    private function saveUpdate(array $update)
    {
        $createdUpdate = UpdatesModel::create([
            'soc' => 13, // ID социальной сети VK
            'json' => $update // RAW данные обновления
        ]);
    }

    /**
     * METHOD: processNewMessage
     * PURPOSE: Специальная обработка новых сообщений для системы ассистента
     * PROCESS:
     * 1. Извлечение данных сообщения из обновления
     * 2. Создание записи в MessagesModel
     * 3. Формирование дополнительной информации о сообщении
     * 4. Сохранение информации о reply сообщениях
     * 5. Помечание обновления как обработанного
     * PARAMETERS: array $update - данные обновления с типом 'message_new'
     */
    private function processNewMessage(array $update)
    {
        // EXTRACT: Извлечение данных сообщения из структуры обновления
        $messageData = $update['object']['message'];
        
        // CREATE: Создание записи сообщения для системы ассистента
        $message = new MessagesModel;
        $message->soc = 13; // ID социальной сети VK
        $message->chat_id = $messageData['peer_id'];
        $message->text = $messageData['text'];
        
        // INFO: Формирование дополнительной информации о сообщении
        $info = [
            'message_id' => $messageData['id'],
            'from' => $messageData['from_id'],
            'data' => $messageData['date'],
            'event_id' => $update['event_id']
        ];

        // REPLY: Обработка reply сообщений
        if (isset($messageData['reply_message'])) {
            $info['reply_to'] = $messageData['conversation_message_id'];
        }

        $message->info = $info;
        $message->save();

        // UPDATE: Помечание обновления как обработанного в базе данных
        UpdatesModel::where('soc', 13)
            ->where('json->event_id', $update['event_id'])
            ->update(['status' => 1]);
    }

    /**
     * METHOD: handleFailedResponse
     * PURPOSE: Обработка failed ответов VK Long Poll API
     * PROCESS:
     * 1. failed: 1 - обновление ts
     * 2. failed: 2,3 - получение новых параметров сервера
     * 3. failed: другие - исключение с неизвестной ошибкой
     * PARAMETERS: array $response - ответ от long polling сервера с полем 'failed'
     * THROWS: \Exception при неизвестных значениях failed
     */
    private function handleFailedResponse(array $response)
    {
        switch ($response['failed']) {
            case 1:
                // UPDATE: Обновление timestamp
                $this->serverParams['ts'] = $response['ts'];
                $this->logator->setType('warning')
                    ->setText('Обновлен ts: ' . $response['ts'])
                    ->write();
                break;
                
            case 2:
            case 3:
                // RECONNECT: Получение новых параметров сервера
                $this->logator->setType('warning')
                    ->setText('Получение новых параметров сервера (failed: ' . $response['failed'] . ')')
                    ->write();
                $this->getNewServerParams();
                break;
                
            default:
                // ERROR: Неизвестная ошибка long polling
                throw new \Exception('Неизвестная ошибка long poll: ' . json_encode($response));
        }
    }

    /**
     * METHOD: handleError
     * PURPOSE: Обработка не критических ошибок выполнения
     * PROCESS:
     * 1. Логирование ошибки с типом 'error'
     * 2. Сообщение об ошибке включает текст исключения
     * PARAMETERS: \Exception $e - объект исключения
     */
    private function handleError(\Exception $e)
    {
        $this->logator->setType('error')
            ->setText('Ошибка: ' . $e->getMessage())
            ->write();
    }
}