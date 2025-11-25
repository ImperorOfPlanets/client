<?php

namespace App\Http\Controllers\Management\Assistant;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

use App\Services\VectorSearchFileService;

use App\Jobs\AI\ProcessEmbedding;
use App\Jobs\AI\VectorSearchJob;
use App\Jobs\AI\ReportEmbeddingsJob;
use App\Jobs\AI\DeleteOrphanedPointsJob;

use App\Models\Ai\AiEmbeddings;

class LearningController extends Controller
{
    protected $categories = [
        1 => 'Товары',
        2 => 'Услуги',
        3 => 'События'
    ];

    public function index()
    {
        $embeddings = AiEmbeddings::query()->latest()->paginate(15);
            
        return view('management.assistant.learning.index', [
            'categories' => $this->categories,
            'embeddings' => $embeddings
        ]);
    }

    public function create()
    {
        return view('management.assistant.learning.create', [
            'categories' => $this->categories
        ]);
    }

    public function store(Request $request)
    {
        // Если пришла команда поиска
        if ($request->filled('command')) {
            $command = $request->input('command');
            
            switch ($command) {
                case 'vector_search':
                    return $this->handleVectorSearch($request);
                
                case 'search_status':
                    return $this->handleSearchStatus($request);
                
                case 'direct_search':
                    return $this->handleDirectSearch($request);
                
                case 'report_embeddings':
                    return $this->handleReportEmbeddings($request);

                case 'report_status':
                    return $this->handleReportStatus($request);
                case 'recreate_vectors':
                    return $this->handleRecreateVectors($request);

                case 'create_vectors':
                    return $this->handleCreateVectors($request);

                case 'delete_embeddings':
                    return $this->handleDeleteEmbeddings($request);

                case 'delete_orphaned_points':
                    return $this->handleDeleteOrphanedPoints($request);
                case 'delete_all_orphaned_points':
                    return $this->handleDeleteAllOrphanedPoints($request);
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Неизвестная команда'
                    ]);
            }
        }

        // Обычное создание эмбеддинга
        $validated = $request->validate([
            'content' => 'required|string|min:20',
            'category' => 'nullable|in:1,2,3',
            'object_id' => 'nullable|integer'
        ]);

        try {
            // Формируем metadata
            $metadata = [
                'category' => [
                    'id' => $validated['category'] ?? null,
                    'name' => $validated['category'] ? $this->categories[$validated['category']] : null
                ],
                'object' => [
                    'id' => $validated['object_id'] ?? null
                ],
                'processing' => [
                    'status' => 'pending'
                ]
            ];

            // Сохраняем эмбеддинг в локальную базу
            $embedding = AiEmbeddings::create([
                'content' => str_replace(["\r\n", "\r"], "\n", $validated['content']),
                'vector_ids' => [], // Пока пустой массив
                'metadata' => $metadata
            ]);

            // Создаем job напрямую
            ProcessEmbedding::dispatch($embedding->id, $embedding->content, [
                'source' => 'learning_system_manual',
                'category' => $embedding->metadata['category']['id'] ?? null,
                'object_id' => $embedding->metadata['object']['id'] ?? null
            ]);            

            return back()->with('success', 'Контент сохранен! Векторное представление создается в фоне.');

        } catch (\Exception $e) {
            Log::error('Error creating embedding', ['error' => $e->getMessage()]);
            return back()->with('error', 'Ошибка при сохранении: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $embedding = AiEmbeddings::findOrFail($id);
    
        // Если пришла команда, обрабатываем отдельно
        if ($request->filled('command')) {
            $command = $request->input('command');
    
            switch ($command) {
                case 'add_to_qdrant':
                    // Если векторов ещё нет
                    if (empty($embedding->vector_ids)) {
                        ProcessEmbedding::dispatch($embedding->id, $embedding->content, [
                            'source' => 'learning_system_manual',
                            'category' => $embedding->metadata['category']['id'] ?? null,
                            'object_id' => $embedding->metadata['object']['id'] ?? null
                        ]);
                        return response()->json(['success' => true, 'message' => 'Задача на создание векторов поставлена в очередь!']);
                    }
                    return response()->json(['success' => false, 'message' => 'Векторы уже созданы']);
                
                case 'recreate':
                    ProcessEmbedding::dispatch($embedding->id, $embedding->content, [
                        'source' => 'learning_system_update',
                        'category' => $embedding->metadata['category']['id'] ?? null,
                        'object_id' => $embedding->metadata['object']['id'] ?? null,
                        'recreate' => true
                    ]);
                    return response()->json(['success' => true, 'message' => 'Пересоздание векторов поставлено в очередь!']);
                
                case 'export':
                    return response()->streamDownload(function() use ($embedding) {
                        echo $embedding->content;
                    }, "embedding-{$embedding->id}.txt");
                
                default:
                    return response()->json(['success' => false, 'message' => 'Неизвестная команда']);
            }
        }
    
        // --- обычное обновление контента ---
        $validated = $request->validate([
            'content' => 'required|string|min:20',
            'category' => 'nullable|in:' . implode(',', array_keys($this->categories)),
            'object_id' => 'nullable|integer'
        ]);
    
        // Обновляем metadata
        $metadata = $embedding->metadata ?? [];
        $metadata['category'] = [
            'id' => $validated['category'] ?? null,
            'name' => $validated['category'] ? $this->categories[$validated['category']] : null
        ];
        $metadata['object'] = [
            'id' => $validated['object_id'] ?? null
        ];
        $metadata['processing']['status'] = 'needs_update';
    
        $embedding->update([
            'content' => str_replace(["\r\n", "\r"], "\n", $validated['content']),
            'metadata' => $metadata
        ]);
    
        // Пересоздаём векторы, если они есть
        if (!empty($embedding->vector_ids)) {
            ProcessEmbedding::dispatch($embedding->id, $validated['content'], [
                'source' => 'learning_system_update',
                'category' => $validated['category'] ?? null,
                'object_id' => $validated['object_id'] ?? null,
                'recreate' => true
            ]);
        }
    
        return redirect()
            ->route('m.assistant.learning.index')
            ->with('success', 'Данные успешно обновлены!');
    }

    public function edit($id)
    {
        $embedding = AiEmbeddings::findOrFail($id);
        
        return view('management.assistant.learning.edit', [
            'embedding' => $embedding,
            'categories' => $this->categories
        ]);
    }

    public function destroy($id)
    {
        $embedding = AiEmbeddings::findOrFail($id);
        
        // TODO: Добавить удаление из Qdrant
        // app(QdrantService::class)->deletePoint($embedding->point_id);
        
        $embedding->delete();

        return redirect()
            ->route('m.assistant.learning.index')
            ->with('success', 'Запись удалена!');
    }


    // ---------------------------------- ДОПОЛНИТЕЛЬНЫЕ ФУНКЦИИ ДЛЯ ПОИСКА

    /**
     * Обработка векторного поиска (асинхронный)
     */
    protected function handleVectorSearch(Request $request)
    {
        $validated = $request->validate([
            'text' => 'required|string|min:3',
            'k' => 'nullable|integer|min:1|max:100'
        ]);

        try {
            $fileService = app(VectorSearchFileService::class); // Инициализируем когда нужен
            $searchId = 'vector_search_' . md5($validated['text'] . time());
            
            // Создаем файл поиска
            $fileService->createSearchFile($searchId, [
                'query' => $validated['text'],
                'k' => $validated['k'] ?? 5,
                'search_type' => 'async'
            ]);

            // Запускаем job
            VectorSearchJob::dispatch(
                $validated['text'],
                $validated['k'] ?? 5,
                $searchId,
                false // асинхронный
            );

            return response()->json([
                'success' => true,
                'message' => 'Запрос на поиск векторов поставлен в очередь',
                'search_id' => $searchId,
                'status' => 'pending'
            ]);

        } catch (\Exception $e) {
            Log::error('Error in vector search', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при выполнении поиска: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Проверка статуса поиска
     */
    protected function handleSearchStatus(Request $request)
    {
        $validated = $request->validate([
            'search_id' => 'required|string'
        ]);

        try {
            $fileService = app(VectorSearchFileService::class); // Инициализируем когда нужен
            $searchData = $fileService->getSearchData($validated['search_id']);
            
            if (!$searchData) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Поиск не найден'
                ]);
            }

            switch ($searchData['status']) {
                case 'pending':
                    return response()->json([
                        'success' => true,
                        'status' => 'pending',
                        'message' => 'Поиск в очереди...'
                    ]);
                
                case 'processing':
                    return response()->json([
                        'success' => true,
                        'status' => 'processing',
                        'message' => 'Поиск выполняется...'
                    ]);
                
                case 'completed':
                    return response()->json([
                        'success' => true,
                        'status' => 'completed',
                        'results' => $searchData['results'] ?? [],
                        'query_text' => $searchData['query'] ?? '',
                        'results_count' => $searchData['results_count'] ?? 0,
                        'processing_time' => $searchData['processing_time'] ?? null,
                        'timestamp' => $searchData['completed_at'] ?? null
                    ]);
                
                case 'error':
                    return response()->json([
                        'success' => false,
                        'status' => 'error',
                        'message' => $searchData['error'] ?? 'Ошибка поиска'
                    ]);
                
                default:
                    return response()->json([
                        'success' => true,
                        'status' => 'pending',
                        'message' => 'Поиск выполняется...'
                    ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Прямой синхронный поиск
     */
    protected function handleDirectSearch(Request $request)
    {
        $validated = $request->validate([
            'text' => 'required|string|min:3',
            'k' => 'nullable|integer|min:1|max:10'
        ]);

        try {
            $searchJob = new VectorSearchJob(
                $validated['text'],
                $validated['k'] ?? 5,
                null,
                true // синхронный
            );
            
            $result = $searchJob->handle();
            
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'results' => $result['results'] ?? [],
                    'query_text' => $result['query_text'] ?? '',
                    'results_count' => $result['results_count'] ?? 0,
                    'timestamp' => $result['timestamp'] ?? null
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'] ?? 'Ошибка поиска'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error in direct vector search', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при выполнении поиска: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Управление файлами поиска (новый метод)
     */
    public function searchFilesManagement(Request $request)
    {
        $action = $request->input('action', 'list');
        $fileService = app(VectorSearchFileService::class); // Инициализируем когда нужен
        
        switch ($action) {
            case 'list':
                $searches = $fileService->getAllSearches(50);
                return response()->json([
                    'success' => true,
                    'searches' => $searches,
                    'total_count' => count($searches)
                ]);
            
            case 'cleanup':
                $deletedCount = $fileService->cleanupOldSearches();
                return response()->json([
                    'success' => true,
                    'message' => "Удалено {$deletedCount} старых файлов поиска"
                ]);
            
            case 'delete':
                $searchId = $request->input('search_id');
                if ($fileService->deleteSearchFile($searchId)) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Файл поиска удален'
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Файл не найден'
                    ]);
                }
            
            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Неизвестное действие'
                ]);
        }
    }

    // ---------------------------------- ДОПОЛНИТЕЛЬНЫЕ ФУНКЦИИ ДЛЯ СИНХРОНИЗАЦИИ

    protected function handleReportEmbeddings(Request $request)
    {
        try {
            $job = new \App\Jobs\AI\ReportEmbeddingsJob();
            dispatch($job);

            return response()->json([
                'success' => true,
                'message' => 'Отчёт по эмбеддингам строится',
                // Передаём абсолютный путь к файлу
                'report_file' => $job->getReportPath(),
                'status' => 'pending',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error starting report job', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка запуска отчёта: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function handleReportStatus(Request $request)
    {
        $validated = $request->validate([
            'report_file' => 'required|string'
        ]);

        $path = $validated['report_file']; // абсолютный путь

        if (!file_exists($path)) {
            return response()->json([
                'success' => true,
                'status' => 'processing',
                'message' => 'Отчёт ещё строится...'
            ]);
        }

        $jsonContent = file_get_contents($path);
        $data = json_decode($jsonContent, true);

        return response()->json([
            'success' => true,
            'status' => 'completed',
            'results' => $data,
        ]);
    }

    protected function handleRecreateVectors(Request $request)
    {
        $validated = $request->validate([
            'embedding_ids' => 'required|array',
            'embedding_ids.*' => 'integer|exists:ai_embeddings,id'
        ]);

        try {
            $embeddings = AiEmbeddings::whereIn('id', $validated['embedding_ids'])->get();
            
            foreach ($embeddings as $embedding) {
                ProcessEmbedding::dispatch($embedding->id, $embedding->content, [
                    'source' => 'learning_system_update',
                    'category' => $embedding->metadata['category']['id'] ?? null,
                    'object_id' => $embedding->metadata['object']['id'] ?? null,
                    'recreate' => true
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Векторы поставлены в очередь на пересоздание'
            ]);

        } catch (\Exception $e) {
            Log::error('Error recreating vectors', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при пересоздании векторов: ' . $e->getMessage()
            ], 500);
        }
    }

    protected function handleCreateVectors(Request $request)
    {
        $validated = $request->validate([
            'embedding_ids' => 'required|array',
            'embedding_ids.*' => 'integer|exists:ai_embeddings,id'
        ]);

        try {
            $embeddings = AiEmbeddings::whereIn('id', $validated['embedding_ids'])->get();
            
            foreach ($embeddings as $embedding) {
                ProcessEmbedding::dispatch($embedding->id, $embedding->content, [
                    'source' => 'learning_system_manual',
                    'category' => $embedding->metadata['category']['id'] ?? null,
                    'object_id' => $embedding->metadata['object']['id'] ?? null
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Векторы поставлены в очередь на создание'
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating vectors', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании векторов: ' . $e->getMessage()
            ], 500);
        }
    }

    protected function handleDeleteEmbeddings(Request $request)
    {
        $validated = $request->validate([
            'embedding_ids' => 'required|array',
            'embedding_ids.*' => 'integer|exists:ai_embeddings,id'
        ]);

        try {
            // Используем Job для очистки (удаляет векторы + эмбеддинг)
            CleanupEmbeddingVectorsJob::dispatch($validated['embedding_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Задача на удаление эмбеддингов и их векторов поставлена в очередь. Эмбеддинги: ' . 
                            implode(', ', array_slice($validated['embedding_ids'], 0, 5)) . 
                            (count($validated['embedding_ids']) > 5 ? '...' : '')
            ]);

        } catch (\Exception $e) {
            Log::error('Error dispatching embedding deletion', [
                'error' => $e->getMessage(),
                'embedding_ids' => $validated['embedding_ids']
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при постановке задачи удаления: ' . $e->getMessage()
            ], 500);
        }
    }

    protected function handleDeleteOrphanedPoints(Request $request)
    {
        $validated = $request->validate([
            'point_ids' => 'required|array',
            'point_ids.*' => 'integer'
        ]);

        try {
            // Используем Job для асинхронного удаления
            DeleteOrphanedPointsJob::dispatch($validated['point_ids']);

            return response()->json([
                'success' => true,
                'message' => 'Задача на удаление осиротевших точек поставлена в очередь. Будут удалены: ' . 
                            implode(', ', array_slice($validated['point_ids'], 0, 5)) . 
                            (count($validated['point_ids']) > 5 ? '...' : '')
            ]);

        } catch (\Exception $e) {
            Log::error('Error dispatching orphaned points deletion', [
                'error' => $e->getMessage(),
                'point_ids' => $validated['point_ids']
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при постановке задачи удаления: ' . $e->getMessage()
            ], 500);
        }
    }

    protected function handleDeleteAllOrphanedPoints(Request $request)
    {
        try {
            $job = new ReportEmbeddingsJob();
            $results = $job->handle(); // Синхронно получаем отчет
            
            $orphanedPoints = $results['details']['orphaned_points'] ?? [];
            $pointIds = array_column($orphanedPoints, 'point_id');
            
            if (empty($pointIds)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Нет осиротевших точек для удаления'
                ]);
            }

            DeleteOrphanedPointsJob::dispatch($pointIds);

            return response()->json([
                'success' => true,
                'message' => 'Задача на удаление всех осиротевших точек поставлена в очередь. Точек: ' . count($pointIds)
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting all orphaned points', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении осиротевших точек: ' . $e->getMessage()
            ], 500);
        }
    }
}