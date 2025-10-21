<?php
namespace App\Http\Controllers\Management\Settings\Sockets;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Events\PublicNewEvent;
use App\Events\PrivateNewEvent;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SocketsController extends Controller
{
	public function index()
	{
        $activeSessions = DB::table('sessions')
            ->where('last_activity', '>=', Carbon::now()->subMinutes(config('session.lifetime'))->timestamp)
            ->get();

		return view('management.settings.sockets.index',[
            'activeSessions'=>$activeSessions
        ]);
	}

    public function store(Request $request)
	{
        if($request->command=="public")
        {
            // Проверяем, что сообщение передано
            $request->validate([
                'message' => 'required|string|max:255',
                'type' => 'required|in:success,warning,danger', // Указываем тип уведомления
            ]);

            // Формируем данные для события
            $data = [
                'message' => $request->message,
                'type' => $request->type, // Тип уведомления (success, warning, danger)
            ];

            // Отправляем событие в публичный канал
            event(new PublicNewEvent('new.update', $data));

            // Выполняем редирект обратно с флеш-сообщением
            return response()->json([
                'success' => 'Сообщение успешно отправлено в публичный канал.'
            ],JSON_UNESCAPED_UNICODE);
        }
        elseif($request->command=="for")
        {
            // Формируем данные для события
            $data = [
                'message' => $request->message ?? 'Default message', // Если сообщение пустое, используем дефолтное
                'event' => $request->event, // Тип события
            ];
            
            $channel_id = strrev($request->session_id);
            Log::info('Отправка события PrivateNewEvent', ['channel_id' => $channel_id, 'data' => $data]);
            // Отправляем событие в приватный канал для указанного пользователя
            event(new PrivateNewEvent($channel_id, $data));
            Log::info('Событие вызвано');
            // Выполняем редирект обратно с флеш-сообщением
            return response()->json([
                'success' => 'Сообщение успешно отправлено в приватный канал.'
            ],JSON_UNESCAPED_UNICODE);
        }
    }
}