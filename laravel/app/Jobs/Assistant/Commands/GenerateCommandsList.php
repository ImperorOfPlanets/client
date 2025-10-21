<?php
namespace App\Jobs\Assistant\Commands;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use App\Models\Assistant\CommandsModel;

class GenerateCommandsList implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        // Генерируем файл с ключевыми словами для быстрого поиска
        $this->generateKeywordsFile();
        
        // Генерируем файл с разрешениями для проверки доступа
        $this->generatePermissionsFile();
    }

    private function generateKeywordsFile()
    {
        $commands = CommandsModel::with('propertys')
            ->get()
            ->map(function($cmd) {
                $keywords = [];

                $main = $cmd->propertyById(1)?->pivot->value;
                if ($main) {
                    $keywords[] = $main;
                }

                $extra = $cmd->propertyById(8)?->pivot->value;
                if ($extra) {
                    $exploded = array_map('trim', explode(',', $extra));
                    $keywords = array_merge($keywords, $exploded);
                }

                $keywords = array_values(array_unique(array_filter($keywords)));

                return [
                    'id' => $cmd->id,
                    'keywords' => implode(',', $keywords) // ← теперь через запятую
                ];
            })
            ->toArray();

        $json = json_encode($commands, JSON_UNESCAPED_UNICODE);

        // сохраняем в storage/app/commands/
        Storage::disk('local')->put('commands/keywords.json', $json);

        Log::info("GenerateCommandsList: keywords.json создан", [
            'disk' => 'local',
            'path' => 'commands/keywords.json',
            'full_path' => storage_path('app/commands/keywords.json'),
            'count' => count($commands),
        ]);
    }

    private function generatePermissionsFile()
    {
        $permissions = CommandsModel::with('propertys')
            ->get()
            ->map(function($cmd) {
                $accessSettings = $cmd->propertyById(119)?->pivot->value;
                $accessData = $accessSettings ? json_decode($accessSettings, true) : [];

                return [
                    'id' => $cmd->id,
                    'access' => $accessData
                ];
            })
            ->toArray();

        $json = json_encode($permissions, JSON_UNESCAPED_UNICODE);

        // сохраняем в storage/app/commands/
        Storage::disk('local')->put('commands/permissions.json', $json);

        Log::info("GenerateCommandsList: permissions.json создан", [
            'disk' => 'local',
            'path' => 'commands/permissions.json',
            'full_path' => storage_path('app/commands/permissions.json'),
            'count' => count($permissions),
        ]);
    }
}
