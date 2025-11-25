<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Socials\SocialsModel;
use App\Models\Socials\UpdatesModel;
use App\Models\Assistant\MessagesModel;
use VK\Client\VKApiClient;
use App\Helpers\Logs\Logs as Logator;

class VKgetUpdates extends Command
{
    protected $signature = 'command:VKgetUpdates';
    protected $description = 'Run VK bot using long polling';

    private $vk;
    private $settings;
    private $logator;
    private $serverParams = [
        'server' => null,
        'key' => null,
        'ts' => null
    ];

    // Константы для настройки поведения
    private const WAIT_TIME = 25; // Время ожидания long polling (сек)
    private const ERROR_DELAY = 5; // Задержка при ошибках (сек)
    private const MAX_RETRIES = 3; // Максимальное количество попыток

    public function __construct()
    {
        parent::__construct();
        $this->logator = new Logator;
        $this->logator->setAuthor('VKgetUpdates - ' . getmypid());
        $this->vk = new VKApiClient('5.131');
    }

    public function handle()
    {
        $this->logator->setType('info')->setText('Запуск обработчика VK')->write();
        $this->logator->setType('info')->setText('PID процесса: ' . getmypid())->write();

        while (true) {
            try {
                $this->logMemoryUsage('Начало цикла');
                
                // Получаем настройки соцсети
                $this->settings = $this->getSettings();
                if (!$this->settings) {
                    sleep(self::ERROR_DELAY);
                    continue;
                }

                // Проверяем статус VK
                if (!$this->checkVKStatus()) {
                    sleep(self::ERROR_DELAY);
                    continue;
                }

                // Инициализируем параметры сервера
                $this->initializeServerParams();

                // Основной цикл получения обновлений
                $this->getUpdatesLoop();

            } catch (\Exception $e) {
                $this->handleError($e);
                sleep(self::ERROR_DELAY);
            }
        }
    }

    private function getSettings()
    {
        try {
            $settings = SocialsModel::find(13);
            
            if (!$settings) {
                $this->logator->setType('error')
                    ->setText('Настройки VK не найдены в БД (ID 13)')
                    ->write();
                return null;
            }
            
            return $settings;
            
        } catch (\Exception $e) {
            $this->logator->setType('error')
                ->setText('Ошибка получения настроек: ' . $e->getMessage())
                ->write();
            return null;
        }
    }

    private function logMemoryUsage(string $context = '')
    {
        $memory = memory_get_usage(true);
        $memoryMb = round($memory / 1024 / 1024, 2);
        $this->logator->setType('debug')
            ->setText("{$context} - Использование памяти: {$memoryMb} MB")
            ->write();
    }

    private function getUpdatesLoop()
    {
        $retryCount = 0;
        
        while (true) {
            try {
                $this->logator->setType('debug')->setText('Запрос обновлений...')->write();
                
                $response = $this->getUpdates();
                
                // Сброс счетчика попыток при успешном запросе
                $retryCount = 0;
                
                if ($response === null) {
                    continue;
                }
                
                $this->processUpdates($response);
                
            } catch (\Exception $e) {
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

    private function checkVKStatus(): bool
    {
        $status = $this->settings->propertyById(116)->pivot->value ?? null;
        
        if (!$status) {
            $this->logator->setType('warning')
                ->setText('VK отключен в настройках. Ожидание включения...')
                ->write();
            return false;
        }

        return true;
    }

    private function initializeServerParams()
    {
        $this->serverParams = [
            'server' => $this->settings->propertyById(46)->pivot->value ?? null,
            'key' => $this->settings->propertyById(47)->pivot->value ?? null,
            'ts' => $this->settings->propertyById(48)->pivot->value ?? null
        ];

        $this->logator->setType('debug')
            ->setText('Текущие параметры сервера: ' . json_encode($this->serverParams))
            ->write();

        // Если каких-то параметров нет, получаем новые
        if (in_array(null, $this->serverParams, true)) {
            $this->getNewServerParams();
        }
    }

    private function getNewServerParams()
    {
        $access_token = $this->settings->propertyById(45)->pivot->value ?? null;
        $group_id = $this->settings->propertyById(40)->pivot->value ?? null;

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
            $data = $this->vk->groups()->getLongPollServer($access_token, [
                'group_id' => $group_id
            ]);

            $this->serverParams = [
                'server' => $data['server'],
                'key' => $data['key'],
                'ts' => $data['ts']
            ];

            // Сохраняем новые параметры
            $this->settings->propertys()->updateExistingPivot(46, ['value' => $data['server']]);
            $this->settings->propertys()->updateExistingPivot(47, ['value' => $data['key']]);
            $this->settings->propertys()->updateExistingPivot(48, ['value' => $data['ts']]);

            $this->logator->setType('success')
                ->setText('Параметры сервера успешно обновлены')
                ->write();

        } catch (\Exception $e) {
            $this->logator->setType('error')
                ->setText('Ошибка получения параметров сервера: ' . $e->getMessage())
                ->write();
            throw $e;
        }
    }

    private function getUpdates()
    {
        $params = [
            "act" => 'a_check',
            "key" => $this->serverParams['key'],
            "ts" => $this->serverParams['ts'],
            "wait" => self::WAIT_TIME,
            "mode" => 2,
            "version" => 3
        ];

        $url = $this->serverParams['server'] . '?' . http_build_query($params, '', '&');
        
        $context = stream_context_create([
            'http' => [
                'timeout' => self::WAIT_TIME + 5 // Таймаут больше чем wait
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            $error = error_get_last();
            throw new \Exception("Ошибка HTTP запроса: " . ($error['message'] ?? 'Неизвестная ошибка'));
        }

        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Ошибка декодирования JSON: " . json_last_error_msg());
        }

        if (isset($data['failed'])) {
            $this->handleFailedResponse($data);
            return null;
        }

        return $data;
    }

    private function processUpdates(array $data)
    {
        // Обновляем ts
        if (isset($data['ts'])) {
            $this->serverParams['ts'] = $data['ts'];
            $this->settings->propertys()->updateExistingPivot(48, ['value' => $data['ts']]);
        }

        if (empty($data['updates'])) {
            return;
        }

        $count = count($data['updates']);
        $this->logator->setType('info')
            ->setText("Получено обновлений: $count")
            ->write();

        foreach ($data['updates'] as $update) {
            try {
                $this->saveUpdate($update);
                
                if ($update['type'] === 'message_new') {
                    $this->processNewMessage($update);
                }
            } catch (\Exception $e) {
                $this->logator->setType('error')
                    ->setText('Ошибка обработки обновления: ' . $e->getMessage())
                    ->write();
            }
        }
    }

    private function saveUpdate(array $update)
    {
        $createdUpdate = UpdatesModel::create([
            'soc' => 13,
            'json' => $update
        ]);
    }

    private function processNewMessage(array $update)
    {
        $messageData = $update['object']['message'];
        
        $message = new MessagesModel;
        $message->soc = 13;
        $message->chat_id = $messageData['peer_id'];
        $message->text = $messageData['text'];
        
        $info = [
            'message_id' => $messageData['id'],
            'from' => $messageData['from_id'],
            'data' => $messageData['date'],
            'event_id' => $update['event_id']
        ];

        if (isset($messageData['reply_message'])) {
            $info['reply_to'] = $messageData['conversation_message_id'];
        }

        $message->info = $info;
        $message->save();

        // Помечаем update как обработанный
        UpdatesModel::where('soc', 13)
            ->where('json->event_id', $update['event_id'])
            ->update(['status' => 1]);
    }

    private function handleFailedResponse(array $response)
    {
        switch ($response['failed']) {
            case 1:
                $this->serverParams['ts'] = $response['ts'];
                $this->logator->setType('warning')
                    ->setText('Обновлен ts: ' . $response['ts'])
                    ->write();
                break;
                
            case 2:
            case 3:
                $this->logator->setType('warning')
                    ->setText('Получение новых параметров сервера (failed: ' . $response['failed'] . ')')
                    ->write();
                $this->getNewServerParams();
                break;
                
            default:
                throw new \Exception('Неизвестная ошибка long poll: ' . json_encode($response));
        }
    }

    private function handleError(\Exception $e)
    {
        $this->logator->setType('error')
            ->setText('Ошибка: ' . $e->getMessage())
            ->write();
    }
}