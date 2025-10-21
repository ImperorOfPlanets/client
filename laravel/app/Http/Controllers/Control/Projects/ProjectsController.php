<?php

namespace App\Http\Controllers\Control\Projects;

use App\Http\Controllers\Controller;
use DB;
use Illuminate\Http\Request;
use App\Helpers\Control\Projects\DBSyncLogService;
use App\Models\Core\Objects;
use App\Models\Core\Groups;
use App\Jobs\ForControlProjects\CheckDBSync;
use Illuminate\Support\Facades\Log;

class ProjectsController extends Controller
{
    public $object = null;
    public $group = null;
    public $groupID = 9;
    protected $logService;

    public function __construct(DBSyncLogService $logService)
    {
        $this->logService = $logService;
    }

    public function index()
    {
        $this->group = Groups::find(9);
        return view('control.projects.index',['projects'=>$this->group->objects]);
    }

    public function store(Request $request)
    {
        $object = new Objects;
        $object->save();
        $object->groups()->attach(9);
        $object->propertys()->attach(1, ['value' => $request->name]);
        return response()->json([
            'redirect' => route('projects.installer.index',['project'=>$object->id])
        ],200,[],JSON_UNESCAPED_UNICODE);
    }

    /**
     * Обработка команды проверки БД
     */
    private function handleCheckDb($projectId)
    {
        $logFileName = $this->logService->generateLogFileName();
        
        CheckDBSync::dispatch([
            'id' => $projectId,
            'log_file_name' => $logFileName
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Задача проверки синхронизации запущена',
            'log_file_name' => $logFileName
        ]);
    }

    public function update(Request $request, $id)
    {
        $command = $request->input('command');
        $projectId = $id;

        Log::info("🎯 [ProjectsController] Update method called", [
            'project_id' => $projectId,
            'command' => $command,
            'all_input' => $request->all()
        ]);

        if (!$command) {
            Log::warning("⚠️ [ProjectsController] No command specified");
            return response()->json(['message' => 'Команда не указана'], 400);
        }

        $project = Objects::find($projectId);
        if (!$project) {
            Log::error("❌ [ProjectsController] Project not found", ['project_id' => $projectId]);
            return response()->json(['message' => 'Проект не найден'], 404);
        }

        Log::info("✅ [ProjectsController] Project found", ['project_name' => $project->name ?? 'Unknown']);

        switch ($command) {
            case 'checkDb':
                Log::info("🚀 [ProjectsController] Handling checkDb command");
                return $this->handleCheckDb($projectId);
                
            case 'monitorSyncStatus':
                $logFileName = $request->input('log_file');
                Log::info("🔍 [ProjectsController] Handling monitorSyncStatus", [
                    'log_file' => $logFileName
                ]);
                if (!$logFileName) {
                    Log::warning("⚠️ [ProjectsController] No log file specified for monitoring");
                    return response()->json(['message' => 'Имя лог-файла не указано'], 400);
                }
                return $this->handleMonitorSyncStatus($projectId, $logFileName);
                
            case 'getDifferences':
                $logFileName = $request->input('log_file');
                Log::info("📊 [ProjectsController] Handling getDifferences", [
                    'log_file' => $logFileName
                ]);
                if (!$logFileName) {
                    Log::warning("⚠️ [ProjectsController] No log file specified for differences");
                    return response()->json(['message' => 'Имя лог-файла не указано'], 400);
                }
                return $this->handleGetDifferences($projectId, $logFileName);

            default:
                Log::warning("⚠️ [ProjectsController] Unknown command", ['command' => $command]);
                return response()->json(['message' => 'Неизвестная команда'], 400);
        }
    }

    private function handleMonitorSyncStatus($projectId, $logFileName)
    {
        Log::info("🔍 [ProjectsController] handleMonitorSyncStatus", [
            'project_id' => $projectId,
            'log_file_name' => $logFileName
        ]);

        $statusData = $this->logService->monitorSyncStatus($projectId, $logFileName);
        
        Log::info("📊 [ProjectsController] Status data prepared", [
            'status' => $statusData['status'] ?? 'unknown',
            'message' => $statusData['message'] ?? 'unknown'
        ]);

        return response()->json($statusData);
    }

    /**
     * Обработка команды получения различий
     */
    private function handleGetDifferences($projectId, $logFileName)
    {
        $differences = $this->logService->getDifferences($projectId, $logFileName);
        
        if (isset($differences['error'])) {
            return response()->json(['error' => $differences['error']], 404);
        }

        return response()->json($differences);
    }
}