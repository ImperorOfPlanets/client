<?php

namespace App\Observers;

use App\Models\Socials\UpdatesModel;
use App\Models\Socials\SocialsModel;
use App\Models\Assistant\MessagesModel;
use Illuminate\Support\Facades\Log;

use App\Helpers\Socials\SocialInterface;

class UpdateObserver
{
    public function created(UpdatesModel $update)
    {
        try {
            $handler = $this->resolveHandler($update->soc);
            
            if (!$handler) {
                $update->update(['status' => 2]);
                return;
            }

            $messageData = $handler->processUpdate($update->json);

            if ($messageData) {
                $this->createMessage($messageData);
                $update->update(['status' => 1]);
            } else {
                $update->update(['status' => 2]);
            }

        } catch (\Throwable $e) {
            Log::error('UpdateObserver error', [
                'update_id' => $update->id,
                'error' => $e->getMessage()
            ]);
            $update->update(['status' => 2]);
        }
    }

    private function resolveHandler(int $socId): ?SocialInterface
    {
        $social = SocialsModel::find($socId);
        
        $handlerClass = $social->propertyById(35)->pivot->value ?? null;
        Log::info('OBSERVER UPDATES');
        if (!$handlerClass || !class_exists($handlerClass)) {
            return null;
        }

        $handler = new $handlerClass();
        return $handler instanceof SocialInterface ? $handler : null;
    }

    private function createMessage(array $data): MessagesModel
    {
        return MessagesModel::create([
            'soc' => $data['soc'],
            'chat_id' => $data['chat_id'],
            'text' => $data['text'],
            'info' => $data['info'],
            'attachments' => $data['attachments'],
            'raw_data' => json_encode($data)
        ]);
    }
}