<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

use App\Models\Core\Groups;
use App\Models\Core\Objects;
use App\Models\Core\ObjectPropertys;

class Propertys extends Model
{
	protected $connection= 'core';
	protected $table = 'propertys';

	protected $primaryKey = 'id';

	public $timestamps = false;

	public function groups()
	{
		return $this->belongsToMany(Groups::class, 'groups_propertys', 'property_id', 'group_id')->withPivot('require');
	}

	public function objects()
	{
		return $this->belongsToMany(Objects::class, 'objects_propertys', 'property_id', 'object_id')->withPivot('value');
	}

	public function searchByName($text)
	{
		return $this->where('name','LIKE','%'.mb_strtolower($text).'%')->get();
	}
}