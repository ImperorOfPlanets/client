<?php

namespace App\Http\Controllers\Management\Assistant;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

use App\Jobs\Assistant\Messages\Delete;
use App\Helpers\Socials\SocialInterface;
use App\Models\Assistant\MessagesModel;

class MessagesController extends Controller
{
	public $initializedSocials = [];

    public function index()
    {
        $messages = MessagesModel::orderBy('id', 'desc')->paginate(15);

        foreach ($messages as $message) {
            $message->processing_log = $this->buildProcessingLog($message);
        }

        return view('management.assistant.messages.index', compact('messages'));
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