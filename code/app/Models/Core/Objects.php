<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

use App\Models\Core\Groups;
use App\Models\Core\Propertys;
use App\Models\Core\ObjectPropertys;
use App\Models\Core\Params;

class Objects extends Model
{
	protected $connection= 'core';
	protected $table = 'objects';

	protected $primaryKey = 'id';

	public $timestamps = false;

	public $errors=[];

	//Свойства
	public function propertys()
	{
		return $this->belongsToMany(Propertys::class, 'objects_propertys', 'object_id', 'property_id')->withPivot('value','block','lock','access')->using(ObjectPropertys::class);
	}

	public function propertyById($pid)
	{
		return $this->propertys()->where('property_id',$pid)->where('object_id',$this->id)->first();
	}

	//группы
	public function groups()
	{
		return $this->belongsToMany(Groups::class, 'objects_groups', 'object_id', 'group_id');
	}

	//Параметры
	public function params()
	{
		return $this->belongsToMany(Params::class, 'objects_params', 'object_id', 'param_id')->withPivot('value');
	}

	public function paramById($pid)
	{
		return $this->params()->where('param_id',$pid)->where('object_id',$this->id)->first();
	}
}