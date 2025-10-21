<?php

namespace App\Http\Controllers\Management\Settings\Logs;

use App\Http\Controllers\Controller;
use App\Models\SiteSettings\SiteSettingsModel;
use Illuminate\Http\Request;

use App\Models\Settings\Logs\LogsModel;
use App\Models\Settings\Site\SettingsModel;

class LogsController extends Controller
{
	public function index(Request $request)
	{
		$logs = LogsModel::orderByDesc('id')->with('propertys')->paginate(50);
		if(isset($request->autoupdate))
		{
			return $logs->toJson();
		}
		else
		{
			return view('management.settings.logs.index',[
				'logs'=>$logs
			]);
		}
	}

	public function show($id)
	{
		//Получаем логатор
		$logObject = SettingsModel::find(31);
		//Включенные авторы
		$propertyAuthors = $logObject->propertyById(102);
		$authorsJson = $propertyAuthors->pivot->value;
		$authors = json_decode($authorsJson);
		if(is_null($authors))
		{
			$authors = [];
		}
		//Найденные авторы
		$propertyFindedAuthors = $logObject->propertyById(4);
		$authorsFindedJson = $propertyFindedAuthors->pivot->value;
		$authorsFinded = json_decode($authorsFindedJson);
		if(is_null($authorsFinded))
		{
			$authorsFinded = [];
		}
		return view('management.settings.logs.settings',[
			'authors'=>$authors,
			'findedAuthors'=>$authorsFinded
		]);
	}

	public function store(Request $request)
	{
		if(isset($request->authors))
		{
			$logObject = SettingsModel::find(31);
			$propertyFindedAuthors = $logObject->propertyById(102);
			$propertyFindedAuthors->pivot->value=$request->authors;
			$propertyFindedAuthors->pivot->save();
			//dd($request->authors);
		}
	}
}