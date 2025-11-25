<?php

namespace App\Http\Controllers\Control\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Core\Groups;
use App\Models\Core\Objects;
use App\Models\Core\Propertys;

class ObjectsController extends Controller
{

	public function create(Request $request)
	{
		$groups = Groups::all();
		return view('control.core.objects.create',[
			'groups'=>$groups
		]);
	}

	//Проверка всех обьектов
	public function checkObjects($objects)
	{
		//Перебираем обьекты
		foreach($objects as $kO => $object)
		{
			$propertysInGroups =[];
			$objects[$kO]->errors=[];

			//Без групп проверяет все свойства
			if($object->groups->count()==0)
			{
				$objects[$kO]->errors[]=[
					'groups'=>null
				];
				//Перебираем свойства
				foreach($object->propertys as $kOP => $property)
				{
					//Получаем сразу значение свойства у обьекта
					$objValue = $object->propertys->keyBy('id')->get($property->id)->pivot->value ?? null;
					if($objValue==null)
					{
						$objects[$kO]->errors[]=[
							'property_id'=>$property->id,
							'error'=>'null',
							'property_name'=>$property->name,
						];
					}
					elseif (trim($objValue)=='')
					{
						$objects[$kO]->errors[]=[
							'property_id'=>$property->id,
							'error'=>'empty',
							'property_name'=>$property->name,
						];
					}
				}
			}
			else
			{
				//Перебираем группы и их свойства
				foreach($object->groups as $kOG => $group)
				{
					//Перебираем свойства группы
					foreach($group->propertys as $kGP => $property)
					{
						$propertysInGroups[]=$property->id;
						//Получаем сразу значение свойства у обьекта
						$objValue = $object->propertys->keyBy('id')->get($property->id)->pivot->value ?? null;//->where('property_id',$property->id)->first();//->pivot->value ?? null;
						//Проверяем на обязательность
						if($property->pivot->require==1)
						{
							if($objValue==null)
							{
								$objects[$kO]->errors[]=[
									'property_id'=>$property->id,
									'error'=>'null require',
									'property_name'=>$property->name,
								];
							}
							elseif (trim($objValue)=='')
							{
								$object[$kO]->errors[]=[
									'property_id'=>$property->id,
									'error'=>'empty require',
									'property_name'=>$property->name,
								];
							}
						}
						else
						{
							if($objValue==null)
							{
								$objects[$kO]->errors[]=[
									'property_id'=>$property->id,
									'error'=>'null',
									'property_name'=>$property->name,
								];
							}
							elseif (trim($objValue)=='')
							{
								$objects[$kO]->errors[]=[
									'property_id'=>$property->id,
									'error'=>'empty',
									'property_name'=>$property->name,
								];
							}
						}
					}
				}
			}

			//Перебираем оставшиеся свойства
			$propertysNotInGroups = $object->propertys->filter(function($value,$key) use ($propertysInGroups){
				return !in_array($value->id,$propertysInGroups);
			});
			//echo '<br />Оставшиеся свойства ';
			foreach($propertysNotInGroups as $property)
			{
				//Получаем сразу значение свойства у обьекта
				$objValue = $object->propertys->keyBy('id')->get($property->id)->pivot->value ?? null;
				if($objValue==null)
				{
					$objects[$kO]->errors[]=[
						'property_id'=>$property->id,
						'error'=>'null',
						'property_name'=>$property->name,
					];
				}
				elseif (trim($objValue)=='')
				{
					$objects[$kO]->errors[]=[
						'property_id'=>$property->id,
						'error'=>'empty',
						'property_name'=>$property->name,
					];
				}
			}
			//dd($propertysNotInGroups);
			//dd($object)
			//dd($checkPropertysInGroups);

			if(count($objects[$kO]->errors)>0)
			{
				$objects[$kO]->errors = json_encode ($objects[$kO]->errors, JSON_UNESCAPED_UNICODE );
			}
			else
			{
				unset($objects[$kO]->errors);
			}
		}
		//return $objects;
	}

	public function index(Request $request)
	{
		if(isset($request->type))
		{
			$type = $request->type;
			if($type=='withoutGroups')
			{
				$objects = Objects::doesntHave('groups')->paginate(15);
			}
			elseif($type=='withoutPropertys')
			{
				$objects = Objects::doesntHave('propertys')->paginate(15);
			}
			elseif($type=='withoutParams')
			{
				$objects = Objects::doesntHave('params')->paginate(15);
			}
		}
		else
		{
			$objects = Objects::paginate(15);
		}
		$this->checkObjects($objects);
		return view('control.core.objects.index',['objects'=>$objects]);
	}

	public function show(Request $request,$id)
	{
		if($id=='getpropertys')
		{
			return (new Propertys)->searchByName($request->search)->toJson(JSON_UNESCAPED_UNICODE);
		}
		if($id=='getgroups')
		{
			return (new Groups)->searchByName($request->search)->toJson(JSON_UNESCAPED_UNICODE);
		}
	}

	public function checkObjectforEdit($object)
	{
		$propertysInGroups =[];
		//Перебираем группы и их свойства
		foreach($object->groups as $kOG => $group)
		{
			//Перебираем свойства группы
			foreach($group->propertys as $kGP => $property)
			{
				$propertysInGroups[]=$property->id;
				//Проверяем наличие записи
				try{
					$objPivot = $object->propertys->keyBy('id')->get($property->id)->pivot;
				}
				catch(\Exception $e)
				{
					$property->error = [
						'type'=>'danger',
						'text'=>'Отсутсвует запись в таблице'
					];
					$object->propertys->push($property);
					continue;
				}
				$objValue = $objPivot->value;
				//Проверяем на обязательность
				if($property->pivot->require==1)
				{
					if(is_null($objValue))
					{
						$pid=$property->id;
						$object->propertys=$object->propertys->map(function ($property,$key) use ($pid)
						{
							if($property->id==$pid)
							{
								$property->error=[
									'type'=>'danger',
									'text'=>'Отсутсвует запись в таблице'
								];
							}
							return $property;
						});
					}
					elseif (trim($objValue)=='')
					{
						$pid=$property->id;
						$object->propertys=$object->propertys->map(function ($property,$key) use ($pid)
						{
							if($property->id==$pid)
							{
								$property->error=[
									'type'=>'danger',
									'text'=>'Пустая строка в таблице'
								];
							}
							return $property;
						});
					}
				}
				else
				{
					if(is_null($objValue))
					{
						$pid=$property->id;
						$object->propertys=$object->propertys->map(function ($property,$key) use ($pid)
						{
							if($property->id==$pid)
							{
								$property->error=[
									'type'=>'danger',
									'text'=>'Null запись в таблице'
								];
							}
							return $property;
						});
					}
					elseif (trim($objValue)=='')
					{
						$pid=$property->id;
						$object->propertys=$object->propertys->map(function ($property,$key) use ($pid)
						{
							if($property->id==$pid)
							{
								$property->error=[
									'type'=>'danger',
									'text'=>'Пустая строка в таблице'
								];
							}
							return $property;
						});
					}
				}
			}
		}

		//Перебираем оставшиеся свойства
		$object->propertys=$object->propertys->map(function ($property,$key) use ($propertysInGroups)
		{
			if(!in_array($property->id,$propertysInGroups))
			{
				//Проверяем наличие записи
				$objValue = $property->pivot->value;
				if(is_null($objValue))
				{
					$property->error=[
						'type'=>'danger',
						'text'=>'Null запись в таблице'
					];
				}
				elseif (trim($objValue)=='')
				{
					$property->error=[
						'type'=>'danger',
						'text'=>'Пустая строка в таблице'
					];
				}
			}
			return $property;
		});
		//dd($object);
		return $object;
	}

	public function edit(Request $request,$id)
	{
		$object=Objects::find($id);
		$object = $this->checkObjectforEdit($object);
		return view('control.core.objects.edit',['object'=>$object]);
	}

	public function update(Request $request,$id)
	{
		if(isset($request->command))
		{
			if($request->command=='addproperty')
			{
				$object = Objects::find($id);
				$object->propertys()->attach($request->property_id, ['value' => $request->value]);
				return response()->json(['refresh' => 1],200,[],JSON_UNESCAPED_UNICODE);
			}
			if($request->command=='addgroup')
			{
				$object = Objects::find($id);
				$object->groups()->attach($request->group_id);
				return response()->json(['refresh' => 1],200,[],JSON_UNESCAPED_UNICODE);
			}
			if($request->command=='deleteproperty')
			{
				$group = Objects::find($id);
				$group->propertys()->detach($request->property_id);
				return response()->json(['refresh' => 1],200,[],JSON_UNESCAPED_UNICODE);
			}
			if($request->command=='deletegroup')
			{
				$group = Objects::find($id);
				$group->groups()->detach($request->group_id);
				return response()->json(['refresh' => 1],200,[],JSON_UNESCAPED_UNICODE);
			}
			if($request->command=='change-property')
			{
				$object = Objects::find($id);
				if($object->propertyById($request->property_id)===null)
				{$object->propertys()->attach($request->property_id, ['value' => $request->value]);}
				else
				{$object->propertys()->updateExistingPivot($request->property_id,['value'=>$request->value]);}
				return response()->json(['message' => 'Значение изменено'],200,[],JSON_UNESCAPED_UNICODE);
				exit;
			}
			if($request->command=='change-params')
			{
				$object = Objects::find($id);
				if($object->paramById($request->param_id)===null)
				{$object->params()->attach($request->param_id, ['value' => $request->value]);}
				else
				{$object->params()->updateExistingPivot($request->param_id,['value'=>$request->value]);}
				return response()->json(['message' => 'Значение изменено'],200,[],JSON_UNESCAPED_UNICODE);
				exit;
			}
			//Запрещает экспорт свойства
			if($request->command=='changeBlock')
			{
				$object = Objects::find($id);
				$object->propertys($request->property_id)->updateExistingPivot($request->property_id,[
					'block'=>filter_var($request->value,FILTER_VALIDATE_BOOLEAN)?1:0
				]);
				return response()->json(['message' => 'Значение изменено'],200,[],JSON_UNESCAPED_UNICODE);
			}
			//Запрещает экспорт значения свойства
			if($request->command=='changeLock')
			{
				$object = Objects::find($id);
				$object->propertys($request->property_id)->updateExistingPivot($request->property_id,[
					'lock'=>filter_var($request->value,FILTER_VALIDATE_BOOLEAN)?1:0
				]);
				return response()->json(['message' => 'Значение изменено'],200,[],JSON_UNESCAPED_UNICODE);
			}
			//Настройка видимости свойства по ролям
			if($request->command=='change-view')
			{
				$object = Objects::find($id);
				$property = $object->propertyById($request->property_id);
				//Если свойство не найдено, то создаем с пустым значением
				if(is_null($property))
				{
					$object->propertys()->attach($request->property_id, ['value' => '']);
				}
				$object->propertys($request->property_id)->updateExistingPivot($request->property_id,[
					'access'=>(array)json_decode($request->value)
				]);
				return response()->json(['message' => 'Значение изменено'],200,[],JSON_UNESCAPED_UNICODE);
			}
			//Получить видимости свойства по ролям
			if($request->command=='getViews')
			{
				$object = Objects::find($id);
				$property = $object->propertyById($request->property_id);
				//dd($object->propertyById($request->property_id)->pivot->show);
				return $object->propertyById($request->property_id)->pivot->access;
			}
		}
	}

	public function store(Request $request)
	{
		$object = new Objects;
		$object->save();
		if(!$request->group_id==null)
		{
			$object->groups()->attach($request->group_id);
		}
		return redirect(route('objects.edit',['object'=>$object->id]));
	}
}