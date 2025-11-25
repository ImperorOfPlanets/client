<?php

namespace App\Http\Controllers\Assistant;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

use App\Models\Assistant\MessagesModel;
use App\Models\Socials\SocialsModel;
use App\Models\Socials\UpdatesModel;

use App\Helpers\Logs\Logs as Logator;

class AssistantController extends Controller
{
    private $logator;

	public function index()
	{
		$sessionID = session()->getId();

		// Инвертируем полную строку сессии
		$reversedSessionID = strrev($sessionID);
		return view('assistant.index',[
			'channel_id'=>$reversedSessionID
		]);
	}

    public function store(Request $request)
    {
        $this->logator = new Logator;
        $this->logator->setAuthor('AssistantController');

        try {
            $updateJSON = [
                'chat_id' => session()->getId(),
                'update_id' => null, // Будет установлено после сохранения
                'date' => now()->timestamp,
            ];
            
            if ($request->hasFile('audio')) {
                $audioFile = $request->file('audio');
                
                if (!$audioFile->isValid()) {
                    throw new \Exception('Invalid audio file');
                }

                $fileName = time() . '.' . $audioFile->getClientOriginalExtension();
                Storage::putFileAs('voice', $audioFile, $fileName);

                $updateJSON['type'] = 'audio';
                $updateJSON['message_type'] = 'voice';
                $updateJSON['file'] = $fileName;
            } elseif ($request->has('text')) {
                $updateJSON['type'] = 'text';
                $updateJSON['text'] = $request->text;
                $updateJSON['message_type'] = 'text';
            } else {
                throw new \Exception('Invalid message type');
            }

            // Создаем запись обновления
            $update = new UpdatesModel;
            $update->soc = 38; // ID ассистента
            $update->status = 1;
            $update->json = $updateJSON;
            $update->save();

            // Добавляем update_id в JSON после сохранения
            $update->json = array_merge($update->json, ['update_id' => $update->id]);
            $update->save();

            $this->logator->setType('success')
                          ->setText('New assistant update created: ' . $update->id)
                          ->write();

            return response()->json([
                'success' => true,
                'update_id' => $update->id
            ]);

        } catch (\Exception $e) {
            $this->logator->setType('danger')
                          ->setText('Error in AssistantController: ' . $e->getMessage())
                          ->write();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

	public function show(Request $request,$id)
	{
		if($id=='messages')
		{
			$messages = MessagesModel::where('soc',38)->where('chat_id',session()->getId())->where('status',5)->orderByDesc('id')->limit(10)->get();
			return response()->json([
                'success' => true,
                'messages' => $messages->toArray()
            ]);
		}
	}
}