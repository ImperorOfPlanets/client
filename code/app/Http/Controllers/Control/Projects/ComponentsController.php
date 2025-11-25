<?php

namespace App\Http\Controllers\Control\Projects;

use App\Http\Controllers\Controller;

use DB;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

use App\Models\Core\Objects;
use App\Models\Core\Groups;

class ComponentsController extends Controller
{
	public $object = null;

    public function __construct()
    {
		//Объект проекта
		$this->object = Objects::find(request()->route()->parameter(array_key_first(request()->route()->parameters())));

		//Внедряем отношение
		Groups::resolveRelationUsing('params',function($orderModel){
			return $orderModel->belongsToMany(\App\Models\Core\Params::class, 'groups_params', 'group_id','param_id')->withPivot('value');
		});
		Objects::resolveRelationUsing('params',function($orderModel){
			return $orderModel->belongsToMany(\App\Models\Core\Params::class, 'objects_params','object_id','param_id')->withPivot('value');
		});

		//Получаем все группы
		$this->groups = Groups::all();
		//Все объекты без групп
		$this->objects = Objects::doesntHave("groups")->get();

		//Удаляем скрытые группы  -> pакрытие должно вернуться true, если элемент должен быть удален из результирующей коллекции
		$this->groups = $this->groups->reject(function($group){
			//Если скрыта группа
			return $group->params()->where('params.id',1)->exists();
		});

		//Удаляем скрытые объекты  -> pакрытие должно вернуться true, если элемент должен быть удален из результирующей коллекции
		$this->objects = $this->objects->reject(function($object){
			//Если скрыта группа
			return $object->params()->where('params.id',1)->exists();
		});

		//Объединяем
		$this->components = $this->groups->merge($this->objects);
		unset($this->objects);unset($this->groups);
    }

	public function index()
	{
		return view('control.projects.components.index',[
			'components'=>$this->components
		]);
	}
}