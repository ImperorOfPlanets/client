<?php
 
namespace App\Http\Middleware;
 
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use App\Models\Users\RolesModel;
 
class Management
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
		$roles = RolesModel::all();
		$froles = [];
		$user_id = session()->get('user_id');
		if(!is_null($user_id))
		{
			foreach($roles as $role)
			{
				$ids = $role->propertyById(83)->pivot->value ?? null;
				try
				{
					if(!is_null($ids) && json_validate($ids))
					{
						if(in_array(session()->get('user_id'),json_decode($ids)))
						{
							$froles[] = $role->id;
						}
					}
				}
				catch(\Exception $e)
				{
					dd($e);
				}
			}
			if(count($froles)>0)
			{
				$request->session()->put('roles',$froles);
				return $next($request);
			}
		}
		abort(404);
    }
}