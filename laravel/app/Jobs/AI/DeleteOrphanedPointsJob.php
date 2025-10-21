<?php

namespace App\Jobs\AI;

use App\Services\QdrantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeleteOrphanedPointsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;

    protected array $pointIds;

    public function __construct(array $pointIds)
    {
        $this->pointIds = $pointIds;
    }

    public function handle()
    {
        Log::info('Starting orphaned points deletion', [
            'point_ids_count' => count($this->pointIds),
            'point_ids' => $this->pointIds
        ]);

        try {
            $qdrantService = app(QdrantService::class);
            
            if (!$qdrantService->isConfigured()) {
                Log::error('Qdrant service not configured for point deletion');
                return;
            }

            $deletedCount = 0;
            $errors = [];

            foreach ($this->pointIds as $pointId) {
                try {
                    $success = $qdrantService->deletePoint($pointId);
                    if ($success) {
                        $deletedCount++;
                        Log::debug('Point deleted successfully', ['point_id' => $pointId]);
                    } else {
                        $errors[] = $pointId;
                        Log::warning('Failed to delete point', ['point_id' => $pointId]);
                    }
                } catch (\Exception $e) {
                    $errors[] = $pointId;
                    Log::error('Exception deleting point', [
                        'point_id' => $pointId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Orphaned points deletion completed', [
                'deleted_count' => $deletedCount,
                'error_count' => count($errors),
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process orphaned points deletion', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}