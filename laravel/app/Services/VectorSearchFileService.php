<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class VectorSearchFileService
{
    protected string $storagePath = 'vector_searches';
    protected int $retentionDays = 7; // Храним файлы 7 дней

    public function __construct()
    {
        // Создаем директорию если не существует
        if (!Storage::exists($this->storagePath)) {
            Storage::makeDirectory($this->storagePath);
        }
    }

    /**
     * Создает файл поиска со статусом "pending"
     */
    public function createSearchFile(string $searchId, array $searchData): bool
    {
        $data = [
            'search_id' => $searchId,
            'status' => 'pending',
            'query' => $searchData['query'] ?? '',
            'k' => $searchData['k'] ?? 5,
            'created_at' => now()->toISOString(),
            'started_at' => null,
            'completed_at' => null,
            'results' => null,
            'error' => null,
            'metadata' => [
                'text_preview' => mb_substr($searchData['query'] ?? '', 0, 100),
                'text_length' => mb_strlen($searchData['query'] ?? ''),
                'search_type' => $searchData['search_type'] ?? 'async'
            ]
        ];

        return $this->saveSearchFile($searchId, $data);
    }

    /**
     * Обновляет статус поиска
     */
    public function updateSearchStatus(string $searchId, string $status, array $additionalData = []): bool
    {
        $data = $this->getSearchFile($searchId);
        
        if (!$data) {
            Log::warning('Search file not found for update', ['search_id' => $searchId]);
            return false;
        }

        $data['status'] = $status;
        $data['updated_at'] = now()->toISOString();

        if ($status === 'processing' && !$data['started_at']) {
            $data['started_at'] = now()->toISOString();
        }

        if ($status === 'completed' && !$data['completed_at']) {
            $data['completed_at'] = now()->toISOString();
        }

        if ($status === 'error') {
            $data['completed_at'] = now()->toISOString();
        }

        $data = array_merge($data, $additionalData);

        return $this->saveSearchFile($searchId, $data);
    }

    /**
     * Сохраняет результаты поиска
     */
    public function saveSearchResults(string $searchId, array $results): bool
    {
        return $this->updateSearchStatus($searchId, 'completed', [
            'results' => $results,
            'results_count' => count($results),
            'processing_time' => $this->calculateProcessingTime($searchId)
        ]);
    }

    /**
     * Сохраняет ошибку поиска
     */
    public function saveSearchError(string $searchId, string $error): bool
    {
        return $this->updateSearchStatus($searchId, 'error', [
            'error' => $error
        ]);
    }

    /**
     * Получает данные поиска
     */
    public function getSearchData(string $searchId): ?array
    {
        return $this->getSearchFile($searchId);
    }

    /**
     * Удаляет файл поиска
     */
    public function deleteSearchFile(string $searchId): bool
    {
        $filename = $this->getFilename($searchId);
        return Storage::delete($filename);
    }

    /**
     * Очищает старые файлы поиска
     */
    public function cleanupOldSearches(): int
    {
        $files = Storage::files($this->storagePath);
        $deletedCount = 0;
        $cutoffDate = now()->subDays($this->retentionDays);

        foreach ($files as $file) {
            $timestamp = Storage::lastModified($file);
            $fileDate = Carbon::createFromTimestamp($timestamp);

            if ($fileDate->lessThan($cutoffDate)) {
                Storage::delete($file);
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * Получает список всех поисков
     */
    public function getAllSearches(int $limit = 50): array
    {
        $files = Storage::files($this->storagePath);
        $searches = [];

        // Сортируем по времени изменения (новые сначала)
        usort($files, function($a, $b) {
            return Storage::lastModified($b) - Storage::lastModified($a);
        });

        $files = array_slice($files, 0, $limit);

        foreach ($files as $file) {
            $content = Storage::get($file);
            $data = json_decode($content, true);
            
            if ($data) {
                $data['file_size'] = Storage::size($file);
                $data['file_name'] = basename($file);
                $searches[] = $data;
            }
        }

        return $searches;
    }

    /**
     * Рассчитывает время выполнения
     */
    protected function calculateProcessingTime(string $searchId): ?float
    {
        $data = $this->getSearchFile($searchId);
        
        if (!$data || !$data['started_at'] || !$data['completed_at']) {
            return null;
        }

        $started = Carbon::parse($data['started_at']);
        $completed = Carbon::parse($data['completed_at']);

        return $completed->diffInSeconds($started, true);
    }

    /**
     * Сохраняет файл поиска
     */
    protected function saveSearchFile(string $searchId, array $data): bool
    {
        $filename = $this->getFilename($searchId);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return Storage::put($filename, $json);
    }

    /**
     * Читает файл поиска
     */
    protected function getSearchFile(string $searchId): ?array
    {
        $filename = $this->getFilename($searchId);
        
        if (!Storage::exists($filename)) {
            return null;
        }

        $content = Storage::get($filename);
        return json_decode($content, true);
    }

    /**
     * Генерирует имя файла
     */
    protected function getFilename(string $searchId): string
    {
        return $this->storagePath . '/' . $searchId . '.json';
    }
}