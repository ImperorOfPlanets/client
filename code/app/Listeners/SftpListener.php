<?php
namespace App\Listeners;

use phpseclib3\Net\SFTP;
use Illuminate\Support\Facades\Log;
use Laravel\Reverb\Events\MessageReceived;
use Illuminate\Support\Facades\Config;
use App\Models\Core\Objects;

class SftpListener
{
    private Objects $objectsModel;
    private ?SFTP $sftpConnection = null;

    public function __construct(Objects $objectsModel)
    {
        $this->objectsModel = $objectsModel;
    }

    public function handle(MessageReceived $eventData): void
    {
        $payload = json_decode($eventData->message);
        $channel = $payload->channel ?? null;

        if ($payload->event === 'start_upload') {
            $this->handleUploadStart($payload->data, $channel, $eventData->connection);
        } elseif ($payload->event === 'upload_chunk') {
            $this->handleChunkUpload($payload->data, $channel, $eventData->connection);
        }
    }

    private function getSftpConnection($project): SFTP
    {
        if ($this->sftpConnection === null) {
            $sftpConfig = $this->getSftpConfig($project);
            
            $this->sftpConnection = new SFTP($sftpConfig['host'], $sftpConfig['port']);
            $this->sftpConnection->setTimeout($sftpConfig['timeout']);

            if (!$this->sftpConnection->login($sftpConfig['username'], $sftpConfig['password'])) {
                throw new \Exception('SFTP login failed');
            }
        }

        return $this->sftpConnection;
    }

    private function handleUploadStart(\stdClass $data, string $channel, $connection): void
    {
        try {
            $projectId = $this->extractProjectId($channel);
            if ($projectId === null) {
                Log::error('Невозможно извлечь ID проекта из канала', ['channel' => $channel]);
                $connection->send(json_encode([
                    'channel' => $channel,
                    'event' => '.upload_error',
                    'data' => ['error' => 'Failed to extract project ID']
                ]));
                return;
            }

            $project = $this->objectsModel->find($projectId);
            if (!$project) {
                Log::error('Проект не найден', ['project_id' => $projectId]);
                $connection->send(json_encode([
                    'channel' => $channel,
                    'event' => '.upload_error',
                    'data' => ['error' => 'Project not found']
                ]));
                return;
            }

            $dirPath = dirname($data->remote_path);
            $sftp = $this->getSftpConnection($project);

            if (!$sftp->file_exists($dirPath)) {
                $sftp->mkdir($dirPath, 0755, true);
            }

            $connection->send(json_encode([
                'event' => 'UploadStarted',
                'channel' => $channel,
                'data' => $data
            ]));
        } catch (\Exception $e) {
            Log::error('Ошибка при подготовке к загрузке файла:', [$e->getMessage()]);
            $connection->send(json_encode([
                'channel' => $channel,
                'event' => 'UploadError',
                'data' => ['error' => 'Failed to prepare upload']
            ]));
        }
    }

    private function handleChunkUpload(\stdClass $data, string $channel, $connection): void
    {
        try {
            Log::info('Начало обработки загрузки чанка', ['channel' => $channel, 'data' => $data]);
            
            // Проверяем необходимые параметры
            if (!isset($data->remote_path)) {
                throw new \Exception('Свойство remote_path отсутствует');
            }
            
            $projectId = $this->extractProjectId($channel);
            if ($projectId === null) {
                throw new \Exception('Невозможно извлечь ID проекта');
            }
            
            $project = $this->objectsModel->find($projectId);
            if (!$project) {
                throw new \Exception('Проект не найден');
            }
            
            // Получаем или создаём новое SFTP соединение
            $sftp = $this->getSftpConnection($project);
            
            // Обрабатываем чанк
            if (!isset($data->chunk) || !is_array($data->chunk)) {
                throw new \Exception('Неверный формат чанка');
            }
            
            $chunkArray = array_map('intval', $data->chunk);
            $chunk = pack('C*', ...$chunkArray);
            
            $currentOffset = $data->offset ?? 0;
            $filePath = $data->remote_path;
            
            // Если файл существует, добавляем новый чанк
            if ($sftp->file_exists($filePath)) {
                // Записываем данные в удаленный файл
                $result = $sftp->put($filePath, $chunk, SFTP::SOURCE_STRING | SFTP::RESUME);
            } else {
                // Создаём новый файл
                $result = $sftp->put($filePath, $chunk, SFTP::SOURCE_STRING);
            }
            
            if (!$result) {
                throw new \Exception("Failed to upload chunk: " . $sftp->getLastError());
            }
            
            // Обновляем прогресс
            $totalSize = $data->totalSize ?? 0;
            $progress = min(100, (($currentOffset + strlen($chunk)) / $totalSize) * 100);
            
            // Отправляем обновление прогресса
            $connection->send(json_encode([
                'channel' => $channel,
                'event' => 'UploadProgress',
                'data' => [
                    'progress' => $progress,
                    'offset' => $currentOffset + strlen($chunk)
                ]
            ]));
            
            // Проверяем завершение загрузки
            if ($currentOffset + strlen($chunk) >= $totalSize) {
                Log::info('Загрузка файла завершена', ['file_path' => $filePath]);
                $connection->send(json_encode([
                    'channel' => $channel,
                    'event' => 'FileUploaded',
                    'data' => ['path' => $filePath]
                ]));
            }
        } catch (\Exception $e) {
            Log::error('Ошибка при загрузке чанка файла:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $connection->send(json_encode([
                'channel' => $channel,
                'event' => 'UploadError',
                'data' => ['error' => $e->getMessage()]
            ]));
        }
    }

    private function extractProjectId($channel): ?int
    {
        if (!$channel) {
            return null;
        }

        $channel = str_starts_with($channel, 'private-') ? 
            substr($channel, 8) : 
            $channel;

        $parts = explode('.', $channel);

        if (count($parts) < 3 || $parts[0] !== 'control' || $parts[1] !== 'projects') {
            return null;
        }

        return (int)$parts[2];
    }

    private function getSftpConfig($project): array
    {
        return [
            'host' => $project->propertyById(31)->pivot->value,
            'username' => $project->propertyById(33)->pivot->value,
            'password' => $project->propertyById(34)->pivot->value,
            'port' => (int)$project->propertyById(32)->pivot->value,
            'timeout' => 30
        ];
    }
}
        