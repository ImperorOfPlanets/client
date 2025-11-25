<?php

namespace App\Http\Controllers\Control\Core;

use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;

use App\Models\Core\Groups;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use ZipArchive;

class InstructionsController extends Controller
{
    public function index()
    {
        $files = scandir(storage_path('app/instructions/actuals'));
        $instructions = [];
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $filename = basename($file);
            if (preg_match('/^(core|client)_(\d{14})\.html$/', $filename, $matches)) {
                $instructions[] = [
                    'type' => $matches[1],
                    'version' => $matches[2],
                    'filename' => $filename,
                    'created_at' => Carbon::createFromFormat('YmdHis', $matches[2])->format('Y-m-d H:i:s'),
                ];
            }
        }
        
        usort($instructions, fn($a, $b) => $b['version'] <=> $a['version']);
        
        return view('control.core.instructions.index', compact('instructions'));
    }

    public function store(){
        $this->generate_for_core();
        return redirect()->back()->with('success', 'Обе инструкции успешно сгенерированы!'); 
    }

    public function generate_for_core()
    {
        $version = now()->format('YmdHis');

        $body = view("control.core.instructions.templates.core.core_gen",[
            'groups'=>Groups::all(),
        ])->render();

        // Генерация контента
        $content = view("control.core.instructions.templates.template",[
            'body'=>$body
        ])->render();

        return $content;
    }

    public function show($filename)
    {
        // Разбиваем параметр на части по _
        $parts = explode('_', $filename);

        // Минимальное количество частей должно быть равно двум
        if (count($parts) < 2) {
            abort(404); // Недостаточно данных
        }

        // Первая часть — операция (d — скачивание, s — показ)
        $action = $parts[0];

        // Вторая часть — тип инструкции (core или client)
        $type = $parts[1];

        // Третья часть — имя файла (может отсутствовать)
        $filename = isset($parts[2]) ? $parts[2] : '';

        //Генерируем названание вызолва для функции
        $function_name = 'generate_for_'. $type;

        //Вызываем нужную генерацию
        $content = $this->$function_name();

        if($action=='s')
        {
            return $content;
        }
        elseif($action=="d")
        {
            $fullPath = storage_path("app/instructions/actuals/{$type}/{$filename}");
            return response()->download($fullPath, $filename)->deleteFileAfterSend(true);
        }
        else{
            abort(404);
        }
    }

    private function archiveOldInstructions()
    {
        $files = scandir(storage_path('app/instructions/actuals'));
        $files = array_filter($files, fn($f) => !in_array($f, ['.', '..']));
        
        if (count($files) <= 20) return;
        
        // Сортируем файлы по возрастанию версии
        usort($files, fn($a, $b) => basename($a) <=> basename($b));
        
        $toArchive = array_slice($files, 0, count($files) - 20);
        $archiveName = 'archive_'.now()->format('YmdHis').'.zip';
        $zip = new ZipArchive();
        
        if ($zip->open(storage_path("app/instructions/archive/{$archiveName}"), ZipArchive::CREATE) === TRUE) {
            foreach ($toArchive as $file) {
                $zip->addFile(
                    storage_path("app/instructions/actuals/{$file}"),
                    $file
                );
                
                unlink(storage_path("app/instructions/actuals/{$file}"));
            }
            $zip->close();
        }
    }
}