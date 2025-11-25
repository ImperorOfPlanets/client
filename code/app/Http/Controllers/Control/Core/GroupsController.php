<?php

namespace App\Http\Controllers\Control\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Core\Groups;
use App\Models\Core\Objects;
use App\Models\Core\Propertys;


class GroupsController extends Controller
{

	public function create(Request $request)
	{
		return view('control.core.groups.create');
	}

	public function index(Request $request)
	{
		if(isset($request->type))
		{
			$type = $request->type;
			if($type=='withoutObjects')
			{
				$groups = Groups::doesntHave('objects')->paginate(15);
			}
			elseif($type=='withoutPropertys')
			{
				$groups = Groups::doesntHave('propertys')->paginate(15);
			}
			elseif($type=='withoutParams')
			{
				$groups = Groups::doesntHave('params')->paginate(15);
			}
		}
		else
		{
			$groups = Groups::paginate(15);
		}
		return view('control.core.groups.index',[
			'groups'=>$groups
		]);
	}

	public function edit(Request $request, $id)
	{
		$group = Groups::find($id);
		return view('control.core.groups.edit',[
			'group'=>$group
		]);
	}

	public function update(Request $request,$id)
	{
		if(isset($request->command))
		{
			if($request->command=='addproperty')
			{
				$group = Groups::find($id);
				$group->propertys()->attach($request->property_id, ['require' => filter_var($request->require,FILTER_VALIDATE_BOOLEAN)?1:0]);
				return response()->json(['refresh' => 1],200,[],JSON_UNESCAPED_UNICODE);
			}
			if($request->command=='deleteproperty')
			{
				$group = Groups::find($id);
				$group->propertys()->detach($request->property_id);
				return response()->json(['refresh' => 1],200,[],JSON_UNESCAPED_UNICODE);
			}
			if($request->command=='change-gp-desc')
			{
				$group = Groups::find($id);
				$group->propertys($request->property_id)->updateExistingPivot($request->property_id,[
					'desc'=>$request->text
				]);
				return response()->json(['message' => 'Значение изменено'],200,[],JSON_UNESCAPED_UNICODE);
			}
			//Изменить описание
			if($request->command=='change-desc')
			{
				$group = Groups::find($id);
				$group->desc=$request->text;
				$group->save();
				return response()->json(['message' => 'Значение изменено'],200,[],JSON_UNESCAPED_UNICODE);
			}
			//Обязательность для заполнения
			if($request->command=='change-require')
			{
				$group = Groups::find($id);
				$group->propertys($request->property_id)->updateExistingPivot($request->property_id,[
					'require'=>filter_var($request->require,FILTER_VALIDATE_BOOLEAN)?1:0
				]);
				return response()->json(['message' => 'Значение изменено'],200,[],JSON_UNESCAPED_UNICODE);
			}
			//Блокировка на выгрузку
			if($request->command=='change-block')
			{
				$group = Groups::find($id);
				$group->propertys($request->property_id)->updateExistingPivot($request->property_id,[
					'block'=>filter_var($request->block,FILTER_VALIDATE_BOOLEAN)?1:0
				]);
				return response()->json(['message' => 'Значение изменено'],200,[],JSON_UNESCAPED_UNICODE);
			}
			//Настройка видимости свойства по ролям
			if($request->command=='change-view')
			{
				$group = Groups::find($id);
				$group->propertys($request->property_id)->updateExistingPivot($request->property_id,[
					'access'=>$request->value
				]);
				return response()->json(['message' => 'Значение изменено'],200,[],JSON_UNESCAPED_UNICODE);
			}
			//Получить видимости свойства по ролям
			if($request->command=='getViews')
			{
				$group = Groups::find($id);
				return $group->propertyById($request->property_id)->pivot->access;
			}
			exit;
		}
		$group = Groups::find($id);
		$group->name= $request->name;
		$group->save();
		return redirect('/control/core/groups/'.$id.'/edit');
	}

	public function show(Request $request,$id)
	{
		if($id=='getpropertys')
		{
			return (new Propertys)->searchByName($request->search)->toJson(JSON_UNESCAPED_UNICODE);
		}
	}

	public function store(Request $request)
	{
		$group = new Groups;
		$group->name= $request->name;
		$group->save();
		return redirect('/control/core/groups');
	}

	public function destroy(Request $request, $id)
	{
		$group = Groups::find($id);
		if($group->propertys->count()>0)
		{
			return response()->json(['error' =>'Невозможно удалить имеются приклепленные свойства'],200,[],JSON_UNESCAPED_UNICODE);
			exit;
		}
		if($group->objects->count()>0)
		{
			return response()->json(['error' =>'Невозможно удалить имеются приклепленные обьекты'],200,[],JSON_UNESCAPED_UNICODE);
			exit;
		}

		$group->delete();
		return redirect('/control/core/groups/');
	}
}