<?php
namespace App\Http\Controllers\Management\Assistant;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Models\Settings\Site\SettingsModel;
use App\Models\Socials\SocialsModel;

use App\Models\Propertys;

use App\Helpers\Editor\Editor;

class SettingsController extends Controller
{

	public function index()
	{
		//Получаем настройки ддя ботов
		$objectsForBots = [
			4, //Переводчик
			17, //Распознавание голосовых команд
		];
		$settings = SettingsModel::whereIn('id',$objectsForBots)->get();

		//Поолучаем все соц сети которые поддерживают ботов
		$socials = SocialsModel::with('propertys')
			->whereHas('propertys',function($q){
				$q->where('propertys.id',115);
			})->get();

		//Проверяем установку
		foreach($socials as $k=>$social)
		{
			$nameClass = $social->propertys->where('id',35)->first()->pivot->value;
			$socClass = new $nameClass;
			$socials[$k]['install']=$socClass->checkInstall();
		}
		return view('management.assistant.settings.index',[
			'settings'=>$settings,
			'socials'=>$socials
		]);
	}

	public function edit($id)
	{
		//Объект редактирования
		$object = SocialsModel::find($id);
		$editor = new Editor($object);
		return view('management.editor',[
			'editor'=>$editor,
			'urlForUpdate'=>$this->getUrlForUpdate()
		]);
	}

	//Показывает все настройки
	public function show(Request $request,$id)
	{
		//Объект показа
		$object = SocialsModel::find($id);

		//Получаем массив ролей
		$roles = session()->get('roles');

		//Отправляем не администраторов
		if(!in_array(20,$roles))
		{
			return redirect('/');
		}

		//Получаем все стандартные поля группы
		$fields = $object->fields();

		//Проверенные свойства
		$checked = [];

		//Свойства к показу
		$checked = [];
		$forShow = [];
		foreach($fields as $field)
		{
			//Получаем описание
			$desc = json_decode($field->params)->desc ?? '';

			//ПОлучаем свойство
			$property = $object->propertyById($field->property_id);
			if(is_null($property))
			{
				$property = Propertys::find($field->property_id);
				$property->desc = $desc;
				$forShow[] = $property;
			}
			else
			{
				$forShow[]=$property;
			}
			$checked[] = $field->property_id;
		}

		//Получаем свойства дополнительные объекта
		$extra = $object->propertys()->whereNotIn('property_id',$checked)->get();
		foreach($extra as $field)
		{
			$forShow[]= $field;
			
		}

		return view('management.shower',[
			'object'=>$object,
			'forShow'=>$forShow
		]);
	}

	public function update(Request $request,$id)
	{
		$object = SocialsModel::find($id);
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
			$url = $url.'/'.$param.'s';
			if(isset($current->parameters[$param]))
			{
				$url = $url.'/'.$current->parameters[$param];
			}
		}
		return '/'.Route::getCurrentRoute()->getPrefix().$url;
	}
}