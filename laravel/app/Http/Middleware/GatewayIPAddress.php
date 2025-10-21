<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use Illuminate\Support\Facades\Cache;

class GatewayIPAddress
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Список разрешенных IP адресов
        $allowedIPs = Cache::get('ips');

        // Получаем IP адрес клиента
        $clientIp = $request->ip();

        // Проверяем, входит ли IP адрес клиента в список разрешенных
        if (!in_array($clientIp, $allowedIPs)) {
            if($clientIp=='127.0.0.1')
            {
                return $next($request);
            }
            // Если нет, отправляем 403 ошибку
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        // Если IP адрес разрешен, продолжаем обработку запроса
        return $next($request);
    }
}
