<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

use App\Helpers\Telegram;
use App\Helpers\Airport;
use App\Helpers\Translator;

use Illuminate\Support\Facades\Bus;

use App\Jobs\ForControlProjects\genCSVforCheckFiles;
use App\Jobs\ForControlProjects\sendCSVForCheckFiles;
use App\Jobs\ForControlProjects\runCommandOnProject;

class MainController extends Controller
{

	public function index()
	{
		return view('control.index');
	}

	public function inteam()
	{
		return view('control.reqsinteam.inteam');
	}

	public function test()
	{
		//Аэропорт
		//$this->air = new Airport;
		//$this->air->getChanges();

		//Погода
		//$weather = new \App\Helpers\Weather();
		//dd($weather->getWeather());

		//Телеграмм
		//$tg = new Telegram;
		//$tg->publishPost([
		//	'text'=>'test',
		//	'group_id'=>'@vlangepase_group',
		//	'attachments'=>[
		//		['path'=>storage_path('app/airport.jpeg')]
		//	]
		//]);

		//Переводчик
		//$translateClass = new Translator(['text'=>'Как у вас дела?']);
		//$result = $translateClass->getTranslate();

		//Работник создания файла CSV для проверки
		$params = [
			//id или массив id проектов для которых создаем список файлов и папок на проверку
			'ids'=>18,
			//'ids'=>18,
			//Список папок определенных к проверкам
		//	'forCheck'=>[
			//	'app','lang'
			//],
			//Добавлять файлы не найденные в оригинале
			//Если true добавит файлы которые не найдены в оригинале на запрос
			//'showFilesNotFoundInOriginal'=>true
		];
		//$result = genCSVforCheckFiles::dispatch($params);



		//Работник отправки файлов
		$params = [
			'ids'=>[18,16]
		];
		Bus::chain([
			//Генерируем CSV
			new genCSVforCheckFiles(['ids'=>18,'showFilesNotFoundInOriginal'=>true]),
			//Отправляем файл для проверки
			new sendCSVforCheckFiles(['ids'=>18]),
			//Запускаем на проекте проверку
			new runCommandOnProject(['ids'=>18,'command'=>'checkFilesInProject']),
			//Получаем результат и удаляем файлы
			//new getResult(['id'=>$this->object->id]),
		])->dispatch();
		//sendCSVForCheckFiles($params);
	}
}
