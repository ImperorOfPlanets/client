<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Longman\TelegramBot\Telegram;
use App\Models\Socials\SocialsModel;
use App\Models\Socials\UpdatesModel;
use App\Helpers\Logs\Logs as Logator;

/**
 * CLASS: TGgetUpdates
 * PURPOSE: Команда для получения обновлений Telegram через long polling
 * DESCRIPTION: Обрабатывает входящие сообщения Telegram, сохраняет их в базу данных и управляет циклом опроса серверов Telegram
 * CATEGORY: command (Laravel Artisan Command)
 * USES: [Laravel, Telegram Bot API, Longman Telegram Bot SDK, Eloquent ORM]
 */
class TGgetUpdates extends Command
{
    /**
     * SIGNATURE: command:TGgetUpdates
     * DESCRIPTION: Сигнатура команды Artisan для запуска через php artisan command:TGgetUpdates
     */
    protected $signature = 'command:TGgetUpdates';

    /**
     * DESCRIPTION: Run Telegram bot using long polling
     * USAGE: Обработчик Telegram бота через механизм long polling
     */
    protected $description = 'Run Telegram bot using long polling';

    /**
     * PROPERTY: lastOffset
     * TYPE: integer
     * PURPOSE: Хранение ID последнего обработанного обновления для пагинации
     * DEFAULT: -1 (начальное значение)
     */
    private $lastOffset = -1;

    /**
     * PROPERTY: telegram
     * TYPE: Telegram
     * PURPOSE: Экземпляр Telegram бота для взаимодействия с API
     */
    private $telegram;

    /**
     * PROPERTY: settings
     * TYPE: SocialsModel
     * PURPOSE: Настройки социальной сети Telegram из базы данных
     */
    private $settings;

    /**
     * PROPERTY: logator
     * TYPE: Logator
     * PURPOSE: Логгер для записи событий и ошибок
     */
    private $logator;

    /**
     * METHOD: __construct
     * PURPOSE: Инициализация команды и логгера
     * PROCESS: Устанавливает автора логов с идентификатором процесса
     */
    public function __construct()
    {
        parent::__construct();
        $this->logator = new Logator;
        $this->logator->setAuthor('TGgetUpdates - ' . getmypid());
    }

    /**
     * METHOD: handle
     * PURPOSE: Основной метод выполнения команды
     * PROCESS: 
     * 1. Бесконечный цикл работы команды
     * 2. Проверка статуса Telegram в настройках
     * 3. Инициализация Telegram бота
     * 4. Цикл получения обновлений с обработкой ошибок
     * RETURNS: Command::SUCCESS (0) или Command::FAILURE (1)
     */
    public function handle()
    {
        while (true) {
            // PROCESS: Получение настроек социальной сети Telegram (ID: 12)
            $this->settings = SocialsModel::find(12);
            
            // PROCESS: Проверка активации Telegram в настройках системы
            if (!$this->checkTelegramStatus()) {
                $this->logator->setType('info')->setText('Telegram отключен, остановка.')->write();
                return 0; // Command::SUCCESS
            }

            // PROCESS: Основной рабочий цикл команды
            try {
                $this->initializeTelegram();
                $this->telegram->useGetUpdatesWithoutDatabase();

                // PROCESS: Внутренний цикл непрерывного получения обновлений
                while (true) {
                    try {
                        $this->getUpdates();
                        $this->adjustSleepTime();
                    } catch (\Exception $e) {
                        $this->handleError($e);
                    }
                }
            } catch (\Exception $e) {
                // ERROR: Критическая ошибка инициализации или выполнения
                $this->logator->setType('danger');
                $this->logator->setText('Критическая ошибка: ' . $e->getMessage());
                $this->logator->write();
                return 1; // Command::FAILURE
            }
        }
    }

    /**
     * METHOD: checkTelegramStatus
     * PURPOSE: Проверка активности Telegram в настройках системы
     * PROCESS: 
     * 1. Получение значения свойства 116 (статус активации)
     * 2. Логирование статуса
     * 3. Возврат результата проверки
     * RETURNS: boolean (активен/неактивен)
     */
    private function checkTelegramStatus(): bool
    {
        // DATA: Получение значения свойства активации через связующую таблицу
        $status = $this->settings->propertyById(116)->pivot->value ?? null;
        
        // PROCESS: Проверка наличия и значения статуса
        if (!$status) {
            $this->logator->setType('warning');
            $this->logator->setText('Telegram отключен в настройках');
            $this->logator->write();
            return false;
        }

        // LOG: Запись успешного запуска
        $this->logator->setType('success')->setText('Telegram запущен')->write();
            
        return true;
    }

    /**
     * METHOD: initializeTelegram
     * PURPOSE: Инициализация клиента Telegram бота
     * PROCESS:
     * 1. Создание экземпляра Telegram с токеном и именем бота
     * 2. Установка пути для загрузки файлов
     * 3. Обработка ошибок инициализации
     * THROWS: \Exception при ошибках инициализации
     */
    private function initializeTelegram()
    {
        try {
            // CONFIG: Создание Telegram клиента с credentials из настроек
            $this->telegram = new Telegram(
                $this->settings->propertyById(30)->pivot->value, // access_token
                $this->settings->propertyById(39)->pivot->value  // bot_username
            );
            
            // CONFIG: Установка пути для скачивания медиафайлов
            $this->telegram->setDownloadPath(storage_path('app/temp/'));
            
        } catch (\Exception $e) {
            // ERROR: Логирование ошибки инициализации
            $this->logator->setType('danger');
            $this->logator->setText('Ошибка инициализации: ' . $e->getMessage());
            $this->logator->write();
            throw $e;
        }
    }

    /**
     * METHOD: getUpdates
     * PURPOSE: Получение обновлений от Telegram API
     * PROCESS:
     * 1. Получение последнего offset для пагинации
     * 2. Запрос к Telegram API за обновлениями
     * 3. Обработка успешного ответа или ошибок
     */
    private function getUpdates()
    {
        $this->lastOffset = $this->getLastOffset();

        try {
            // API: Запрос обновлений с использованием последнего offset
            $response = $this->telegram->handleGetUpdates($this->lastOffset);
            
            // PROCESS: Проверка успешности ответа API
            if ($response->isOk()) {
                $this->processUpdates($response->getResult());
            } else {
                // WARNING: Логирование ошибки API Telegram
                $this->logator->setType('warning');
                $this->logator->setText('Ошибка получения: ' . $response->printError());
                $this->logator->write();
            }
        } catch (\Longman\TelegramBot\Exception\TelegramException $e) {
            $this->handleError($e);
        }
    }

    /**
     * METHOD: processUpdates
     * PURPOSE: Обработка полученных обновлений
     * PROCESS:
     * 1. Подсчет количества обновлений
     * 2. Логирование статистики
     * 3. Сохранение каждого обновления в базу данных
     * PARAMETERS: array $updates - массив объектов обновлений
     */
    private function processUpdates(array $updates)
    {
        // DATA: Подсчет количества полученных обновлений
        $count = count($updates);
        $this->logator->setType('success');
        $this->logator->setText("Получено обновлений: $count");
        $this->logator->write();

        // PROCESS: Сохранение каждого обновления в базу данных
        foreach ($updates as $update) {
            UpdatesModel::create([
                'soc' => 12, // ID социальной сети Telegram
                'json' => $update->getRawData() // RAW данные обновления
            ]);
        }
    }

    /**
     * METHOD: getLastOffset
     * PURPOSE: Получение ID последнего обработанного обновления
     * PROCESS:
     * 1. Поиск последнего обновления в базе данных
     * 2. Расчет следующего offset для пагинации
     * RETURNS: integer - следующий offset или -1 если обновлений нет
     */
    private function getLastOffset()
    {
        // DATABASE: Поиск последнего обновления для социальной сети 12
        $lastUpdate = UpdatesModel::where('soc', 12)
            ->latest('id')
            ->first();
            
        // CALCULATION: Расчет следующего offset (текущий update_id + 1)
        return $lastUpdate ? $lastUpdate->json['update_id'] + 1 : -1;
    }

    /**
     * METHOD: adjustSleepTime
     * PURPOSE: Регулировка времени паузы между запросами
     * PROCESS:
     * - 5 секунд при первом запуске (offset = -1)
     * - 1 секунда при последующих запросах
     * STRATEGY: Оптимизация частоты запросов к API
     */
    private function adjustSleepTime()
    {
        sleep($this->lastOffset === -1 ? 5 : 1);
    }

    /**
     * METHOD: handleError
     * PURPOSE: Обработка не критических ошибок выполнения
     * PROCESS:
     * 1. Логирование ошибки
     * 2. Пауза 5 секунд перед повторной попыткой
     * PARAMETERS: \Exception $e - объект исключения
     */
    private function handleError(\Exception $e)
    {
        $this->logator->setType('danger');
        $this->logator->setText('Ошибка: ' . $e->getMessage());
        $this->logator->write();
            
        sleep(5);
    }
}