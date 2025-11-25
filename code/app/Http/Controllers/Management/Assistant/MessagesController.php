<?php

namespace App\Http\Controllers\Management\Assistant;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

use App\Jobs\Assistant\Messages\Delete;

use App\Helpers\Socials\SocialInterface;

use Carbon\Carbon;

use App\Models\Assistant\MessagesModel;
use App\Models\Socials\SocialsModel;

class MessagesController extends Controller
{
	public $initializedSocials = [];

    public function index(Request $request)
    {
        $query = MessagesModel::query()->orderBy('id', 'desc');
        
        // Применяем фильтры, если они переданы
        if ($request->has('soc') && $request->soc) {
            $query->where('soc', $request->soc);
        }
        
        if ($request->has('chat_id') && $request->chat_id) {
            $query->where('chat_id', $request->chat_id);
        }

        $messages = $query->paginate(15);
        
        // Добавляем параметры фильтрации к пагинации
        if ($request->has('soc') || $request->has('chat_id')) {
            $messages->appends([
                'soc' => $request->soc,
                'chat_id' => $request->chat_id
            ]);
        }

        // Получаем все социальные сети с их названиями (property_id = 1)
        $socials = SocialsModel::with(['propertys' => function($query) {
            $query->where('property_id', 1);
        }])->get()->mapWithKeys(function($social) {
            $name = $social->propertys->first()->pivot->value ?? 'Неизвестная сеть';
            return [$social->id => $name];
        })->toArray();

        foreach ($messages as $message) {
             $message->social_name = $socials[$message->soc] ?? 'Неизвестная сеть';
            $message->processing_log = $this->buildProcessingLog($message);
        }

        return view('management.assistant.messages.index', compact('messages', 'socials'));
    }

    public function show($id)
    {
        $message = MessagesModel::findOrFail($id);
        
        // Получаем название социальной сети
        $social = SocialsModel::with(['propertys' => function($query) {
            $query->where('property_id', 1);
        }])->find($message->soc);
        
        $social_name = $social->propertys->first()->pivot->value ?? 'Неизвестная сеть';

        return response()->json([
            'message' => [
                'id' => $message->id,
                'text' => $message->text,
                'soc' => $message->soc,
                'social_name' => $social_name,
                'chat_id' => $message->chat_id,
                'status' => $message->status,
            ],
            'info_json' => json_encode($message->info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        ]);
    }

	public function destroy(Request $request,$id)
	{
		Bus::chain([
			//Удаляем сообщение
			new	Delete(['message_id'=>$id]),
		])->dispatch();
		return redirect()->back();
	}

    private function buildProcessingLog(MessagesModel $message): array
    {
        $info = $message->info ?? [];
        $processingStructure = [];

        $socialClass = $this->getSocialClassBySoc($message->soc);

        $createdDate = null;
        if ($socialClass && $socialClass->checkMessageCreatedDate($info)) {
            $createdDate = $socialClass->getMessageCreatedDate($info);
        }
        if ($createdDate) {
            $processingStructure[] = [
                'stage' => 'Дата создания сообщения',
                'status' => '',
                'result' => $createdDate,
                'timestamp' => $createdDate,
            ];
        }

        $updatedDate = null;
        if ($socialClass && $socialClass->checkMessageUpdatedDate($info)) {
            $updatedDate = $socialClass->getMessageUpdatedDate($info);
        }

        $notifyTimestamp = $updatedDate ?? null;
        if (isset($info['processing_message_id'])) {
            $processingStructure[] = [
                'stage' => 'Отправлено на фильтрацию',
                'status' => 'выполнено',
                'result' => 'ID уведомления: ' . $info['processing_message_id'],
                'timestamp' => $notifyTimestamp,
            ];
        }

        if (isset($info['filters']) && is_array($info['filters'])) {
            foreach ($info['filters'] as $filterName => $filterData) {
                $timestamp = $filterData['processed_at'] ?? null;
                if (is_numeric($timestamp)) {
                    $timestamp = Carbon::createFromTimestamp($timestamp)->toDateTimeString();
                }
                $processingStructure[] = [
                    'stage' => "Фильтр: $filterName",
                    'status' => $filterData['status'] ?? 'неизвестно',
                    'result' => $filterData['result'] ?? null,
                    'timestamp' => $timestamp,
                ];
            }
        }

        if (isset($info['processing_messages']) && is_array($info['processing_messages'])) {
            foreach ($info['processing_messages'] as $msg) {
                $timestamp = $msg['timestamp'] ?? null;
                if (is_numeric($timestamp)) {
                    $timestamp = Carbon::createFromTimestamp($timestamp)->toDateTimeString();
                }
                $processingStructure[] = [
                    'stage' => $msg['stage'] ?? 'Этап',
                    'status' => $msg['status'] ?? '',
                    'result' => $msg['description'] ?? '',
                    'timestamp' => $timestamp,
                ];
            }
        }

        usort($processingStructure, function ($a, $b) {
            $ta = $a['timestamp'] ?? '';
            $tb = $b['timestamp'] ?? '';
            if ($ta === $tb) return 0;
            if ($ta === '') return 1;
            if ($tb === '') return -1;
            return strcmp($ta, $tb);
        });

        return $processingStructure;
    }

    private function getSocialClassBySoc($socId): ?SocialInterface
    {
        if (isset($this->initializedSocials[$socId])) {
            return $this->initializedSocials[$socId];
        }

        $social = \App\Models\Socials\SocialsModel::find($socId);
        if (!$social) {
            return null;
        }

        $classPath = $social->propertyById(35)->pivot->value ?? null;
        if (!$classPath || !class_exists($classPath)) {
            return null;
        }

        $instance = new $classPath;
        $this->initializedSocials[$socId] = $instance;
        return $instance;
    }

}