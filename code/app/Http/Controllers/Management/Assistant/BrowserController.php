<?php

namespace App\Http\Controllers\Management\Assistant;

use App\Http\Controllers\Controller;
use App\Services\BrowserContainerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BrowserController extends Controller
{
    private $browserService;

    public function __construct(BrowserContainerService $browserService)
    {
        $this->browserService = $browserService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('management.assistant.browser.index', [
            'serviceInfo' => $this->browserService->getDebugInfo(),
            'recentCommands' => session('recent_commands', [])
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('management.assistant.browser.create', [
            'presets' => $this->getCommandPresets()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Если пришла команда
        if ($request->filled('command')) {
            return $this->handleCommand($request);
        }

        // Обычное создание через форму
        $validated = $request->validate([
            'commands' => 'required|array',
            'commands.*.action' => 'required|string',
            'commands.*.url' => 'required_if:commands.*.action,browse|url',
            'commands.*.query' => 'required_if:commands.*.action,search|string',
            'commands.*.selector' => 'sometimes|string',
            'commands.*.value' => 'sometimes|string',
            'meta.source' => 'sometimes|string',
            'meta.description' => 'sometimes|string'
        ]);

        try {
            $result = $this->browserService->executeCommands(
                $validated['commands'],
                $validated['meta'] ?? [],
                $request->input('request_id')
            );

            // Сохраняем в историю
            $this->saveToHistory($validated['commands'], $result);

            if ($request->wantsJson()) {
                return response()->json($result);
            }

            return back()->with([
                'success' => 'Команды выполнены успешно!',
                'execution_result' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Browser command execution failed', ['error' => $e->getMessage()]);

            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'error' => $e->getMessage()
                ], 500);
            }

            return back()->with('error', 'Ошибка выполнения: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        // Для просмотра конкретного результата
        return view('management.assistant.browser.show', [
            'result_id' => $id,
            'serviceInfo' => $this->browserService->getDebugInfo()
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('management.assistant.browser.edit', [
            'scenario_id' => $id,
            'presets' => $this->getCommandPresets()
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        return $this->store($request);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        return back()->with('success', 'Сценарий удален');
    }

    /**
     * Дополнительные методы для API
     */
    public function search(Request $request)
    {
        $query = $request->get('query', 'Илон Маск');
        
        $result = $this->browserService->searchAndExtract(
            $query, 
            'h3', 
            'google'
        );

        return response()->json($result);
    }

    public function debug()
    {
        $debugPage = $this->browserService->getDebugPage();
        $serviceInfo = $this->browserService->getDebugInfo();

        return view('management.assistant.browser.debug', [
            'debugPage' => $debugPage,
            'serviceInfo' => $serviceInfo
        ]);
    }

    public function health()
    {
        $health = $this->browserService->healthCheck();
        
        return response()->json([
            'status' => $health ? 'healthy' : 'unhealthy',
            'service_configured' => $this->browserService->isConfigured(),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Метод для частичного рендеринга результатов
     */
    public function partialResults(Request $request)
    {
        $result = $request->input('result');
        
        return view('management.assistant.browser.partials.results', [
            'result' => $result
        ]);
    }

    /**
     * Обработка специальных команд
     */
    private function handleCommand(Request $request)
    {
        $command = $request->input('command');

        // Проверяем, что сервис настроен
        if (!$this->browserService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Browser service is not configured'
            ], 503);
        }

        switch ($command) {
            case 'quick_search':
                return $this->handleQuickSearch($request);
            
            case 'browse_url':
                return $this->handleBrowseUrl($request);
            
            case 'extract_text':
                return $this->handleExtractText($request);
            
            case 'take_screenshot':
                return $this->handleScreenshot($request);
            
            case 'test_connection':
                return $this->handleTestConnection($request);
            
            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Неизвестная команда'
                ]);
        }
    }

    private function handleQuickSearch(Request $request)
    {
        $validated = $request->validate([
            'query' => 'required|string',
            'engine' => 'sometimes|in:google,yandex,bing',
            'extract_selector' => 'sometimes|string'
        ]);

        $commands = [
            [
                'action' => 'search',
                'query' => $validated['query'],
                'engine' => $validated['engine'] ?? 'google',
                'options' => ['text' => true, 'screenshot' => true]
            ]
        ];

        if (!empty($validated['extract_selector'])) {
            $commands[] = [
                'action' => 'extract_text',
                'selector' => $validated['extract_selector']
            ];
        }

        $result = $this->browserService->executeCommands($commands, [
            'source' => 'quick_search',
            'description' => "Поиск: {$validated['query']}"
        ]);

        $this->saveToHistory($commands, $result);

        return response()->json($result);
    }

    private function handleBrowseUrl(Request $request)
    {
        $validated = $request->validate([
            'url' => 'required|url',
            'screenshot' => 'sometimes|boolean',
            'extract_text' => 'sometimes|boolean',
            'selector' => 'sometimes|string'
        ]);

        $commands = [
            [
                'action' => 'browse',
                'url' => $validated['url'],
                'options' => [
                    'screenshot' => $validated['screenshot'] ?? true,
                    'text' => $validated['extract_text'] ?? false
                ]
            ]
        ];

        if (!empty($validated['selector'])) {
            $commands[] = [
                'action' => 'extract_text',
                'selector' => $validated['selector']
            ];
        }

        $result = $this->browserService->executeCommands($commands, [
            'source' => 'browse_url',
            'description' => "Переход: {$validated['url']}"
        ]);

        $this->saveToHistory($commands, $result);

        return response()->json($result);
    }

    private function handleExtractText(Request $request)
    {
        $validated = $request->validate([
            'selector' => 'required|string',
            'url' => 'sometimes|url'
        ]);

        $commands = [];

        if (!empty($validated['url'])) {
            $commands[] = [
                'action' => 'browse',
                'url' => $validated['url'],
                'options' => ['screenshot' => true]
            ];
        }

        $commands[] = [
            'action' => 'extract_text',
            'selector' => $validated['selector']
        ];

        $result = $this->browserService->executeCommands($commands, [
            'source' => 'extract_text',
            'description' => "Извлечение: {$validated['selector']}"
        ]);

        $this->saveToHistory($commands, $result);

        return response()->json($result);
    }

    private function handleScreenshot(Request $request)
    {
        $validated = $request->validate([
            'full_page' => 'sometimes|boolean',
            'url' => 'sometimes|url'
        ]);

        $commands = [];

        if (!empty($validated['url'])) {
            $commands[] = [
                'action' => 'browse',
                'url' => $validated['url']
            ];
        }

        $commands[] = [
            'action' => 'screenshot',
            'full_page' => $validated['full_page'] ?? false
        ];

        $result = $this->browserService->executeCommands($commands, [
            'source' => 'screenshot',
            'description' => 'Скриншот страницы'
        ]);

        $this->saveToHistory($commands, $result);

        return response()->json($result);
    }

    private function handleTestConnection(Request $request)
    {
        $health = $this->browserService->healthCheck();
        $configured = $this->browserService->isConfigured();
        
        return response()->json([
            'success' => $health && $configured,
            'health_check' => $health,
            'service_configured' => $configured,
            'debug_info' => $this->browserService->getDebugInfo()
        ]);
    }

    /**
     * Вспомогательные методы
     */
    private function getCommandPresets()
    {
        return [
            'simple_search' => [
                'name' => 'Простой поиск',
                'commands' => [
                    [
                        'action' => 'search',
                        'query' => 'Илон Маск',
                        'engine' => 'google',
                        'options' => ['text' => true, 'screenshot' => true]
                    ]
                ]
            ],
            'search_and_extract' => [
                'name' => 'Поиск и извлечение заголовков',
                'commands' => [
                    [
                        'action' => 'search',
                        'query' => 'Новости технологий',
                        'engine' => 'google',
                        'options' => ['text' => true]
                    ],
                    [
                        'action' => 'extract_text',
                        'selector' => 'h3'
                    ]
                ]
            ],
            'browse_and_screenshot' => [
                'name' => 'Переход и скриншот',
                'commands' => [
                    [
                        'action' => 'browse',
                        'url' => 'https://google.com',
                        'options' => ['screenshot' => true]
                    ]
                ]
            ]
        ];
    }

    private function saveToHistory($commands, $result)
    {
        $history = session('recent_commands', []);
        
        $history[] = [
            'commands' => $commands,
            'result' => $result,
            'timestamp' => now()->toISOString()
        ];

        // Храним только последние 10 команд
        if (count($history) > 10) {
            array_shift($history);
        }

        session(['recent_commands' => $history]);
    }
}