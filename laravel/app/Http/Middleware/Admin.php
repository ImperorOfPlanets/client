<?php
 
namespace App\Http\Middleware;
 
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
 
class Admin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
		$user_id = session()->get('user_id');
        if ($user_id === 1 || $user_id === 134) {
            // Пользователь с ID 1 всегда проходит
            return $next($request);
        }
		abort(404);
    }
}