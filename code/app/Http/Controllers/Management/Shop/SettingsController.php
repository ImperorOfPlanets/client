<?php

namespace App\Http\Controllers\Management\Shop;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Models\Socials\SocialsModel;

class SettingsController extends Controller
{
	public function index()
	{
		$socials = SocialsModel::with('propertys')
			->whereHas('propertys',function($q){
				$q->where('propertys.id',115);
			})->get();
		return view('management.assistant.settings.index',[
			'socials'=>$socials
		]);
	}

	public function edit($id)
	{
		$object = SocialsModel::find($id);
		return view('management.editor',[
			'object'=>$object
		]);
	}

	/* Это от настроек пользователя
	public function show(Request $request,$id)
	{
		if($id=='basic')
		{
			return view('management.settings.'.$id.'.index');
		}
		elseif($id=='socials')
		{
			$variables['socials'] = SocialsModel::all();
			foreach($variables['socials'] as $keySocial=>$social)
			{
				//класс в котором вызываем функцию
				//dd($social->propertyById(35)->pivot->value);
				//функция проверки установки
				//dd($social->propertyById(29)->pivot->value);
				try
				{
					$className = $social->propertyById(35)->pivot->value;
				}
				catch(\Exception $e)
				{
					dd($social->propertys);
				}

				$objSocial = new $className;

				$variables['socials'][$keySocial]->installed = $result = $objSocial->checkInstall();
				//dd($result);
			}
			return view('management.settings.'.$id.'.index',$variables);
		}
	}

	public function store(Request $request)
	{
		switch($request->command){
			//Обновление переменной загруженной фотографии
			case 'updatePhoto':
				$files = new Files;
				$result = $files->fileUpload();
				dd($result);
			break;
		}
	}*/
}