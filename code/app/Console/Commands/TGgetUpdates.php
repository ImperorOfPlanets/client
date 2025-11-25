<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Longman\TelegramBot\Telegram;
use App\Models\Socials\SocialsModel;
use App\Models\Socials\UpdatesModel;

use App\Helpers\Logs\Logs as Logator;

class TGgetUpdates extends Command
{
    protected $signature = 'command:TGgetUpdates';
    protected $description = 'Run Telegram bot using long polling';

    private $lastOffset = -1;
    private $telegram;
    private $settings;
    private $logator;

    public function __construct()
    {
        parent::__construct();
        $this->logator = new Logator;
        $this->logator->setAuthor('TGgetUpdates - ' . getmypid());
    }

    public function handle()
    {
        while (true) {
            // Получаем настройки соцсети
            $this->settings = SocialsModel::find(12);
            
            // Проверяем статус Telegram
            if (!$this->checkTelegramStatus()) {
                $this->logator->setType('info')->setText('Telegram отключен, остановка.')->write();
                return 0; // Эквивалент Command::SUCCESS
            }

            // Основной цикл работы
            try {
                $this->initializeTelegram();
                $this->telegram->useGetUpdatesWithoutDatabase();

                while (true) {
                    try {
                        $this->getUpdates();
                        $this->adjustSleepTime();
                    } catch (\Exception $e) {
                        $this->handleError($e);
                    }
                }
            } catch (\Exception $e) {
                $this->logator->setType('danger');
                $this->logator->setText('Критическая ошибка: ' . $e->getMessage());
                $this->logator->write();
                return 1; // Эквивалент Command::FAILURE
            }
        }
    }

    private function checkTelegramStatus(): bool
    {
        $status = $this->settings->propertyById(116)->pivot->value ?? null;
        
        if (!$status) {
            $this->logator->setType('warning');
            $this->logator->setText('Telegram отключен в настройках');
            $this->logator->write();
            return false;
        }

        $this->logator->setType('success')->setText('Telegram запущен')->write();
            
        return true;
    }

    private function initializeTelegram()
    {
        try {
            $this->telegram = new Telegram(
                $this->settings->propertyById(30)->pivot->value, // access_token
                $this->settings->propertyById(39)->pivot->value  // bot_username
            );
            
            $this->telegram->setDownloadPath(storage_path('app/temp/'));
            
        } catch (\Exception $e) {
            $this->logator->setType('danger');
            $this->logator->setText('Ошибка инициализации: ' . $e->getMessage());
            $this->logator->write();
            throw $e;
        }
    }

    private function getUpdates()
    {
        $this->lastOffset = $this->getLastOffset();

        try {
            $response = $this->telegram->handleGetUpdates($this->lastOffset);
            
            if ($response->isOk()) {
                $this->processUpdates($response->getResult());
            } else {
                $this->logator->setType('warning');
                $this->logator->setText('Ошибка получения: ' . $response->printError());
                $this->logator->write();
            }
        } catch (\Longman\TelegramBot\Exception\TelegramException $e) {
            $this->handleError($e);
        }
    }

    private function processUpdates(array $updates)
    {
        $count = count($updates);
        $this->logator->setType('success');
        $this->logator->setText("Получено обновлений: $count");
        $this->logator->write();

        foreach ($updates as $update) {
            UpdatesModel::create([
                'soc' => 12,
                'json' => $update->getRawData()
            ]);
        }
    }

    private function getLastOffset()
    {
        $lastUpdate = UpdatesModel::where('soc', 12)
            ->latest('id')
            ->first();
        return $lastUpdate ? $lastUpdate->json['update_id'] + 1 : -1;
    }

    private function adjustSleepTime()
    {
        sleep($this->lastOffset === -1 ? 5 : 1);
    }

    private function handleError(\Exception $e)
    {
        $this->logator->setType('danger');
        $this->logator->setText('Ошибка: ' . $e->getMessage());
        $this->logator->write();
            
        sleep(5);
    }
}