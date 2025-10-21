<?php

namespace App\Http\Controllers\Management\Users;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Models\Users\RolesModel;

class RolesController extends Controller
{
	public function index()
	{
		
		$roles = RolesModel::all();
		return view('management.users.roles.index',[
			'roles'=>$roles,
			'urlForUpdate'=>$this->getUrlForUpdate()
		]);
	}

	public function edit($id)
	{
		//Объект редактирования
		$object = RolesModel::find($id);

		//Получаем список пользователей
		try
		{
			$users = json_decode($object->propertyById(83)->pivot->value);
		}
		catch(\Exception $e)
		{
			$users=[];
		}
		return view('management.users.roles.edit',[
			'object'=>$object,
			'users'=>$users,
			'urlForUpdate'=>$this->getUrlForUpdate()
		]);
	}

	public function getUrlForUpdate()
	{
		$current = Route::getCurrentRoute();
		$url = '';
		foreach($current->parameterNames as $param)
		{
			$url = $url.'/'.$param.'s';
			if(isset($current->parameters[$param]))
			{
				$url = $url.'/'.$current->parameters[$param];
			}
		}
		return '/'.Route::getCurrentRoute()->getPrefix().$url;
	}
}