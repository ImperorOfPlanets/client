<?php

namespace App\Jobs\AI;

use App\Models\Ai\AiEmbeddings;
use App\Services\QdrantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReportEmbeddingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800;
    public $tries = 2;

    protected string $reportDir = 'embeddings/syncs'; // папка для отчётов
    protected string $reportFile;

    protected array $report = [
        'processed_at' => null,
        'summary' => [
            'total_raw_embeddings' => 0,
            'total_vector_points' => 0,
            'no_vectors' => 0,
            'missing_vectors' => 0,
            'orphaned_points' => 0,
        ],
        'details' => [
            'no_vectors' => [],
            'missing_vectors' => [],
            'orphaned_points' => [],
        ]
    ];

    public function __construct()
    {
        $filename = 'report_' . now()->format('Y_m_d_His') . '.json';
        $this->reportFile = $this->reportDir . '/' . $filename;
    }

    /**
     * Возвращает полный путь к файлу отчёта
     */
    public function getReportPath(): string
    {
        return storage_path('app/' . $this->reportFile);
    }

    /**
     * Главная функция обработки
     */
    public function handle()
    {
        Log::info('Starting embeddings diff report');
        $this->report['processed_at'] = now()->toIso8601String();

        try {
            $rawEmbeddings = AiEmbeddings::all();
            $this->report['summary']['total_raw_embeddings'] = $rawEmbeddings->count();
            Log::info('Loaded raw embeddings', ['count' => $rawEmbeddings->count()]);

            $qdrantService = app(QdrantService::class);
            $allVectorPoints = $this->getAllVectorPoints($qdrantService);
            $this->report['summary']['total_vector_points'] = count($allVectorPoints);
            Log::info('Loaded vector points', ['count' => count($allVectorPoints)]);

            $this->detectNoVectors($rawEmbeddings);
            Log::info('No-vectors detected', ['count' => $this->report['summary']['no_vectors']]);

            $this->detectMissingVectors($rawEmbeddings, $allVectorPoints);
            Log::info('Missing-vectors detected', ['count' => $this->report['summary']['missing_vectors']]);

            $this->detectOrphanedPoints($rawEmbeddings, $allVectorPoints);
            Log::info('Orphaned points detected', ['count' => $this->report['summary']['orphaned_points']]);

        } catch (\Throwable $e) {
            Log::error('Failed to build embeddings report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        $this->saveReport();

        return $this->report;
    }

    /**
     * Сохраняет отчёт на диск через storage_path()
     */
    protected function saveReport(): void
    {
        $this->report = $this->utf8ize($this->report);

        $jsonData = json_encode($this->report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($jsonData === false) {
            Log::error('Failed to encode report to JSON', ['error' => json_last_error_msg()]);
            return;
        }

        $fullDir = storage_path('app/' . $this->reportDir);
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0777, true);
        }

        $saved = file_put_contents($this->getReportPath(), $jsonData);
        if ($saved !== false) {
            Log::info('Report saved successfully', ['file' => $this->getReportPath()]);
        } else {
            Log::error('Failed to save report file', ['file' => $this->getReportPath()]);
        }
    }

    /**
     * Приведение всех данных к UTF-8
     */
    protected function utf8ize($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->utf8ize($value);
            }
        } elseif (is_string($data)) {
            return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        }
        return $data;
    }

    protected function getAllVectorPoints(QdrantService $qdrantService): array
    {
        $allPoints = [];
        $nextPageOffset = null;
        $limit = 1000;

        do {
            $scrollResult = $qdrantService->scrollPoints($nextPageOffset, $limit);
            if (isset($scrollResult['result']['points'])) {
                $points = $scrollResult['result']['points'];
                $allPoints = array_merge($allPoints, $points);
                $nextPageOffset = $scrollResult['result']['next_page_offset'] ?? null;
            } else {
                break;
            }
        } while ($nextPageOffset !== null);

        return $allPoints;
    }

    protected function detectNoVectors($rawEmbeddings): void
    {
        foreach ($rawEmbeddings as $embedding) {
            $vectorIds = $embedding->vector_ids ?? [];
            
            // Если vector_ids пустой, null или не массив
            if (empty($vectorIds) || !is_array($vectorIds)) {
                $this->report['summary']['no_vectors']++;
                $this->report['details']['no_vectors'][] = [
                    'embedding_id' => $embedding->id,
                    'content_preview' => substr($embedding->content, 0, 100),
                    'vector_ids_type' => gettype($embedding->vector_ids),
                    'vector_ids_value' => $embedding->vector_ids
                ];
            }
        }
    }

    protected function detectMissingVectors($rawEmbeddings, array $allVectorPoints): void
    {
        // Создаем карту всех точек в Qdrant по parent_id и chunk_id
        $qdrantPointsMap = [];
        foreach ($allVectorPoints as $point) {
            $payload = $point['payload'] ?? [];
            $parentId = $payload['parent_id'] ?? null;
            $chunkId = $payload['chunk_id'] ?? null;
            
            if ($parentId !== null && $chunkId !== null) {
                $key = "embedding_{$parentId}_chunk_{$chunkId}";
                $qdrantPointsMap[$key] = true;
            }
        }

        foreach ($rawEmbeddings as $embedding) {
            if (!empty($embedding->vector_ids) && is_array($embedding->vector_ids)) {
                $missing = [];
                foreach ($embedding->vector_ids as $vectorId) {
                    // Проверяем, есть ли этот vectorId в Qdrant
                    if (!isset($qdrantPointsMap[$vectorId])) {
                        $missing[] = $vectorId;
                    }
                }
                if ($missing) {
                    $this->report['summary']['missing_vectors']++;
                    $this->report['details']['missing_vectors'][] = [
                        'embedding_id' => $embedding->id,
                        'missing_vector_ids' => $missing,
                        'content_preview' => substr($embedding->content, 0, 100),
                    ];
                }
            }
        }
    }

    protected function detectOrphanedPoints($rawEmbeddings, array $allVectorPoints): void
    {
        // Собираем все vectorIds из базы
        $allKnownVectorIds = [];
        foreach ($rawEmbeddings as $embedding) {
            if (!empty($embedding->vector_ids) && is_array($embedding->vector_ids)) {
                foreach ($embedding->vector_ids as $vectorId) {
                    $allKnownVectorIds[$vectorId] = true;
                }
            }
        }

        foreach ($allVectorPoints as $point) {
            $payload = $point['payload'] ?? [];
            $parentId = $payload['parent_id'] ?? null;
            $chunkId = $payload['chunk_id'] ?? null;
            
            if ($parentId !== null && $chunkId !== null) {
                $vectorId = "embedding_{$parentId}_chunk_{$chunkId}";
                
                // Если vectorId нет в базе - это осиротевшая точка
                if (!isset($allKnownVectorIds[$vectorId])) {
                    $this->report['summary']['orphaned_points']++;
                    $this->report['details']['orphaned_points'][] = [
                        'point_id' => $point['id'] ?? null,
                        'vector_id' => $vectorId,
                        'parent_id' => $parentId,
                        'chunk_id' => $chunkId,
                        'payload_preview' => [
                            'text' => substr($payload['text'] ?? '', 0, 100),
                            'source' => $payload['source'] ?? null
                        ]
                    ];
                }
            } else {
                // Точки без parent_id/chunk_id тоже считаем осиротевшими
                $this->report['summary']['orphaned_points']++;
                $this->report['details']['orphaned_points'][] = [
                    'point_id' => $point['id'] ?? null,
                    'payload' => $payload,
                    'reason' => 'missing_parent_or_chunk_info'
                ];
            }
        }
    }

    public function getResults(): array
    {
        return $this->report;
    }
}
