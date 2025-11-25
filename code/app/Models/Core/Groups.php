<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

use App\Models\Core\Objects;
use App\Models\Core\Propertys;

class Groups extends Model
{
	
	protected $connection= 'core';

	protected $table = 'groups';

	protected $primaryKey = 'id';

	public $timestamps = false;

	//Свойства
	public function propertys()
	{
		return $this->belongsToMany(Propertys::class, 'groups_propertys', 'group_id','property_id')->withPivot('id','require','desc','block','access');
	}

	//Свойство по ID
	public function propertyById($pid)
	{
		return $this->propertys()->where('property_id',$pid)->where('group_id',$this->id)->first();
	}

	//Объекты
	public function objects()
	{
		return $this->belongsToMany(Objects::class, 'objects_groups', 'group_id','object_id');
	}
	
	public function searchByName($text)
	{
		return $this->where('name','LIKE','%'.mb_strtolower($text).'%')->get();
	}

	//Параметры
	public function params()
	{
		return $this->belongsToMany(Params::class, 'groups_params', 'group_id', 'param_id')->withPivot('value');
	}

	public function paramById($pid)
	{
		return $this->params()->where('param_id',$pid)->where('group_id',$this->id)->first();
	}

}