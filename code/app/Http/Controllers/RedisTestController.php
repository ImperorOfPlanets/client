<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class RedisTestController extends Controller
{
    public function test()
    {
        try {
            // Базовая проверка подключения
            $pong = Redis::ping();
            
            // Сохраняем тестовое значение
            Redis::set('test_key', 'test_value');
            
            // Получаем сохраненное значение
            $value = Redis::get('test_key');
            
            return response()->json([
                'status' => 'success',
                'ping_response' => $pong,
                'stored_value' => $value,
                'connection_active' => true
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'connection_active' => false
            ], 500);
        }
    }

    public function monitor()
    {
        try {
            // Получаем информацию о сервере Redis
            $info = Redis::info();
            
            // Получаем список активных ключей
            $keys = Redis::keys('*');
            
            return response()->json([
                'status' => 'success',
                'server_info' => $info,
                'active_keys' => count($keys),
                'keys_list' => $keys
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}