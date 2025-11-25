<?php

namespace App\Http\Controllers\Management\Payments;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Helpers\Editor\Editor;

use App\Models\Payments\ProvaidersModel;

class ProvaidersController extends Controller
{
	public function index()
	{
		$provaiders = ProvaidersModel::paginate(15);
		return view('management.payments.provaides.index',[
			'provaiders'=>$provaiders
		]);
	}

	public function edit(Request $request,$id)
	{
		//Объект редактирования
		$object =ProvaidersModel::find($id);
		$editor = new Editor($object);
		$editor->getAllPropertys();
		//dd($editor);
		return view('management.editor',[
			'object'=>$object,
			'editor'=>$editor,
			'urlForUpdate'=>$this->getUrlForUpdate()
		]);
	}

	public function update(Request $request,$id)
	{
		$object = ProvaidersModel::find($id);
		if($request->command == 'change-property')
		{
			$property = $object->propertyById($request->property_id);
			if(is_null($property))
			{
				$object->propertys()->attach($request->property_id,['value'=>$request->value]);
			}
			else
			{
				$property->pivot->value=$request->value;
				$property->pivot->save();
			}
		}
	}

	public function getUrlForUpdate()
	{
		$current = Route::getCurrentRoute();
		$url = '';
		foreach($current->parameterNames as $param)
		{
			$url = $url.$param.'s';
			if(isset($current->parameters[$param]))
			{
				$url = $url.'/'.$current->parameters[$param];
			}
		}
		return '/'.Route::getCurrentRoute()->getPrefix().$url;
	}
}