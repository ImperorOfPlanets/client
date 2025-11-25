<?php

namespace App\Jobs\AI;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GetEmbedding implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $params;
    protected $teiServers = [
        'http://tei1.example.com:8080',
        'http://tei2.example.com:8080'
    ];

    public function __construct($params = null)
    {
        $this->params = $params;
        $projectName = env('PROJECTNAME', 'core');
        $localServer = "http://tei-{$projectName}:8080";
        array_unshift($this->teiServers, $localServer);

        Log::info('GetEmbedding job initialized', [
            'params' => $this->params,
            'teiServers' => $this->teiServers
        ]);
    }

    public function handle()
    {
        $text = $this->params['text'] ?? null;
        if (!$text) {
            Log::error('Text not provided for embedding generation');
            return;
        }

        $server = $this->getAvailableServer();
        if (!$server) {
            Log::error('No available TEI server found');
            $this->handleError('no_server', 'Нет доступного сервера для генерации эмбеддингов');
            return;
        }

        try {
            $targetUrl = rtrim($server, '/') . '/embeddings';
            
            Log::info('Sending embedding generation request', [
                'server' => $server,
                'target_url' => $targetUrl,
                'text_length' => strlen($text),
                'text_preview' => substr($text, 0, 100) . (strlen($text) > 100 ? '...' : '')
            ]);

            $response = Http::timeout(60)->post($targetUrl, [
                'inputs' => $text,
                'truncate' => true
            ]);

            $responseBody = $response->body();
            $result = $response->json();
            $statusCode = $response->status();

            Log::info('Response from TEI server received', [
                'status_code' => $statusCode,
                'response_size' => strlen($responseBody),
                'has_embedding' => isset($result[0]['embedding'])
            ]);

            if ($statusCode === 200) {
                $this->handleSuccessfulResponse($result, $text);
            } else {
                $this->handleError('server_error', "Сервер вернул ошибку: {$statusCode}");
            }

        } catch (\Exception $e) {
            Log::error('Error requesting TEI server', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->handleError('generation_error', 'Ошибка генерации эмбеддинга: ' . $e->getMessage());
        }
    }

    protected function handleSuccessfulResponse(array $result, string $text): void
    {
        if (!isset($result[0]['embedding'])) {
            $this->handleError('invalid_response', 'Сервер вернул некорректный формат эмбеддинга');
            return;
        }

        $embedding = $result[0]['embedding'];
        $embeddingSize = count($embedding);

        // Сохранение результата
        $this->saveEmbeddingResult($embedding, $text, $embeddingSize);

        Log::info('Embedding generated successfully', [
            'embedding_size' => $embeddingSize,
            'text_length' => strlen($text)
        ]);

        // Если нужно отправить куда-то результат (например, через событие)
        $this->dispatchResult($embedding, $text);
    }

    protected function saveEmbeddingResult(array $embedding, string $text, int $embeddingSize): void
    {
        try {
            // Сохранение в файл (как в оригинальном коде)
            file_put_contents(
                storage_path('embeddings.txt'), 
                json_encode([
                    'text' => $text,
                    'embedding' => $embedding,
                    'size' => $embeddingSize,
                    'timestamp' => now()->toISOString()
                ]) . PHP_EOL, 
                FILE_APPEND
            );

            // Дополнительно можно сохранить в базу данных
            if (isset($this->params['message_id'])) {
                // Логика сохранения привязанная к сообщению
                $this->saveToMessage($embedding, $text);
            }

        } catch (\Exception $e) {
            Log::error('Error saving embedding result', [
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function saveToMessage(array $embedding, string $text): void
    {
        // Пример сохранения эмбеддинга в связанное сообщение
        // Требует доработки в зависимости от вашей структуры БД
        /*
        $message = MessagesModel::find($this->params['message_id']);
        if ($message) {
            $info = $message->info;
            $info['embedding'] = [
                'vector' => $embedding,
                'generated_at' => now()->toISOString(),
                'model' => 'tei'
            ];
            $message->info = $info;
            $message->save();
        }
        */
    }

    protected function dispatchResult(array $embedding, string $text): void
    {
        // Диспатч события или вызов callback'а если нужно
        if (isset($this->params['callback'])) {
            try {
                call_user_func($this->params['callback'], $embedding, $text);
            } catch (\Exception $e) {
                Log::error('Error calling embedding callback', [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    protected function getAvailableServer()
    {
        foreach ($this->teiServers as $server) {
            try {
                $healthUrl = rtrim($server, '/') . '/health';
                $response = Http::timeout(2)->get($healthUrl);

                if ($response->ok()) {
                    return $server;
                }
            } catch (\Exception $e) {
                Log::debug('TEI server health check failed', [
                    'server' => $server,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
        return null;
    }

    protected function handleError(string $errorCode, string $logMessage): void
    {
        Log::error($logMessage, [
            'error_code' => $errorCode,
            'params' => $this->params
        ]);

        // Можно добавить логику уведомления об ошибке
        if (isset($this->params['error_callback'])) {
            try {
                call_user_func($this->params['error_callback'], $errorCode, $logMessage);
            } catch (\Exception $e) {
                Log::error('Error calling error callback', [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}