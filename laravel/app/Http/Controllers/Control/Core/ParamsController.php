<?php

namespace App\Http\Controllers\Control\Core;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

use App\Models\Core\Params;

class ParamsController extends Controller
{
	public $essence;

    public function __construct()
    {
		if(isset(Route::currentRouteName()[0]))
		{
			$essenceName = explode('.',Route::currentRouteName())[0];
			$essencePathClass = "App\\Models\\Core\\".ucfirst($essenceName);
			$essencePathClass::resolveRelationUsing('params',function($orderModel) use ($essenceName) {
				return $orderModel->belongsToMany(\App\Models\Core\Params::class, $essenceName.'_params', array_key_first(request()->route()->parameters()).'_id', 'param_id')->withPivot('value');
			});
			$this->essence = $essencePathClass::find(request()->route()->parameter(array_key_first(request()->route()->parameters())));
			View::share('essence',$this->essence);
		}
    }

	public function index(Request $request)
	{
		$params = $this->essence->params()->paginate(10);
		return view('control.core.params.index',[
			'params'=>$params
		]);
	}

	public function list(Request $request)
	{
		return view('control.core.params.list',[
			'params'=>Params::paginate(10)
		]);
	}

	public function create(Request $request)
	{
		return view('control.core.params.create');
	}

	public function show(Request $request)
	{
		if($request->param=='getparams')
		{
			return (new Params)->searchByName($request->search)->paginate(5)->toJson(JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		}
	}

	public function store(Request $request)
	{
		$param = new Params;
		$param->name = $request->name;
		$param->desc = $request->desc;
		$param->save();
		$param->{$this->essence->getTable()}()->attach($this->essence->id);
		return redirect(route(str_replace('.store','.index',request()->route()->getName()),request()->route()->parameters));
	}

	public function update(Request $request)
	{
		if($request->command=='addparam')
		{
			$this->essence->params()->attach($request->param_id, ['value' => $request->value]);
			return response()->json(['refresh' => 1],200,[],JSON_UNESCAPED_UNICODE);
			exit;
		}
		if($request->command=='change-param')
		{
			$this->essence->params()->updateExistingPivot($request->param_id,['value'=>$request->value]);
			return response()->json(['message' => 'Значение изменено'],200,[],JSON_UNESCAPED_UNICODE);
			exit;
		}
		if($request->command=='delete-param')
		{
			$this->essence->params()->detach($request->param_id);
			return response()->json(['refresh' => 1],200,[],JSON_UNESCAPED_UNICODE);
			exit;
		}
	}

	/*public function update(Request $request)
	{
		$param = new Params;
		$param->desc = $request->desc;
		$param->save();
		$param->{$this->essence->getTable()}()->attach($this->essence->id);
		return redirect(
			str_replace('.store','.index',request()->route()->getName()),
			[array_key_first(request()->route()->parameters())=>$this->essence->id]
		);
	}*/
}