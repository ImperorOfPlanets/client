<?php

namespace App\Jobs\ForControlProjects;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use App\Models\Core\Groups;

class ExportProjects implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $statusValue;
    protected $groupID;

    /**
     * Create a new job instance.
     *
     * @param string $statusValue
     * @param int $groupID
     * @return void
     */
    public function __construct($statusValue, $groupID = 9)
    {
        $this->statusValue = $statusValue;
        $this->groupID = $groupID;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Получаем группу с заданным ID и её объекты с необходимыми свойствами
        $group = Groups::with(['objects.propertys' => function($query) {
            $query->whereIn('property_id', [49, 116]); // Загружаем только свойства с ID 49 (координаты) и 116 (статус)
        }])->find($this->groupID);

        if ($group && $group->objects->isNotEmpty()) {
            $projectsData = $group->objects->map(function($object) {
                // Извлекаем значение свойства со статусом (ID 116)
                $status = $object->propertys->firstWhere('property_id', 116)->pivot->value ?? null;

                // Проверяем, соответствует ли статус требуемому значению
                if ($status !== $this->statusValue) {
                    return null; // Пропускаем, если статус не совпадает
                }

                // Извлекаем значение свойства с координатами (ID 49)
                $coordinates = $object->propertys->firstWhere('property_id', 49)->pivot->value ?? '';
                [$latitude, $longitude] = explode(' ', $coordinates) + [null, null]; // Разделяем координаты по пробелу

                return [
                    'id' => $object->id,
                    'name' => $object->name, // Замените на реальное поле, если оно есть
                    'status' => $status,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ];
            })->filter(); // Убираем null-значения из коллекции

            // Проверяем, что есть данные для отправки
            if ($projectsData->isNotEmpty()) {
                $response = Http::post('https://myidon.site/projects', [
                    'projects' => $projectsData->values()->toArray()
                ]);

                if ($response->failed()) {
                    \Log::error('Ошибка отправки проектов на myidon.site', ['status' => $response->status()]);
                } else {
                    \Log::info('Проекты успешно отправлены на myidon.site');
                }
            } else {
                \Log::info('Нет проектов с указанным статусом для отправки');
            }
        } else {
            \Log::info('Группа не найдена или не содержит объектов с указанными свойствами');
        }
    }
}
