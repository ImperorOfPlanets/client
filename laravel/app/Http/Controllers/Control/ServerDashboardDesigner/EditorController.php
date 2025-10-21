<?php

namespace App\Http\Controllers\Control\ServerDashboardDesigner;

use App\Http\Controllers\Controller;

use App\Models\Core\Objects;
use App\Models\Core\Groups;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class EditorController extends Controller
{
	public $object = null;

    public function __construct()
    {
    }

	public function index()
	{
		$servers = Groups::find(29);
		return view('control.serverdashboarddesigner.editor.index',[
			'servers'=>$servers->objects
		]);
	}

	public function store(Request $request)
	{
		//Измение свойства
		if($request->command == 'changeValue')
		{
			$obj = Objects::find($request->obj);
			//Сохраняем значение
			if($obj->propertyById($request->property)===null)
			{$obj->propertys()->attach($request->property, ['value' => $request->value]);}
			else
			{$obj->propertys()->updateExistingPivot($request->property,['value'=>$request->value]);}
			return response()->json(['message' => 'Значение изменено'],200,[],JSON_UNESCAPED_UNICODE);
		}
		//Вовзращает списк серверов, секций и блоков
		elseif($request->command == 'get')
		{
			$object = Objects::find($request->id);
			$childrens = json_decode($object->propertyById(102)->pivot->value ?? null);

			if($request->receiver=='servers')
			{
				$id = 30;
			}
			else if($request->receiver=='sections')
			{
				$id = 31;
			}
			$full = Groups::find($id)->objects()->with('propertys')->get();
			return response()->json([
				'childrens'=>$childrens,
				'full'=>$full
			]);
		}
		//Возвращает блок
		elseif($request->command == 'getBlock')
		{
			//Проект - пример
			$project = Objects::find(18);

			//Секция
			$block = Objects::find($request->block);
			try
			{
				$pathForClass = 'App\\Helpers\\Control\\Blocks\\'.$block->propertyById(35)->pivot->value;
			}
			catch(\Exception $e)
			{
				$message = $e->getMessage();
				if(str_contains($message,'null'))
				{
					$htmlRes = 'Property 35 not found for section '.$block->id. " <a href='/control/core/objects/$block->id/edit'>Change object</a>";
					return $htmlRes;
				}
			}
			$viewClass = new $pathForClass;
			$viewClass->setIdProject($project);
			$viewClass->setBlock($block);
			$viewClass->processRequest();
			return $viewClass->view();
		}
	}
}