<?php

namespace App\Http\Controllers\Control\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use App\Models\Core\Groups;
use App\Models\Core\Objects;
use App\Models\Core\Propertys;

class PropertysController extends Controller
{

	public function create(Request $request)
	{
		return view('control.core.propertys.create');
	}

	public function index()
	{
		$propertys = Propertys::paginate(15);
		return view('control.core.propertys.index',[
			'propertys'=>$propertys
		]);
	}

	public function show($id)
	{
	}

	public function edit(Request $request, $id)
	{
		$property = Propertys::find($id);
		return view('control.core.propertys.edit',[
			'property'=>$property
		]);
	}

	public function update(Request $request,$id)
	{
		$property = Propertys::find($id);
		if(isset($request->command))
		{
			if($request->command=='change-desc')
			{
				$property->desc = $request->text;
				$property->save();
				return response()->json(['message' => 'Значение изменено'],200,[],JSON_UNESCAPED_UNICODE);
				exit;
			}
		}
		$property->name= $request->name;
		$property->desc=$request->desc;
		$property->save();
		return redirect('/control/core/propertys');
	}

	public function store(Request $request)
	{
		$property = new Propertys;
		$property->name= $request->name;
		$property->desc=$request->desc;
		$property->save();
		if(isset($request->group_id))
		{
			$property->groups()->attach($request->group_id, ['require' => (($request->require)?1:0)]);
			return redirect('/control/core/groups/'.$request->group_id.'/edit');
			exit;
		}
		if(isset($request->object_id))
		{
			$property->objects()->attach($request->object_id, ['value' => $request->value]);
			return redirect('/control/core/objects/'.$request->object_id.'/edit');
			exit;
		}
		return redirect('/control/core/propertys');
	}

	public function destroy(Request $request,$id){
		Propertys::destroy($id);
		return response()->json(['refresh' => 1],200,[],JSON_UNESCAPED_UNICODE);
	}
}