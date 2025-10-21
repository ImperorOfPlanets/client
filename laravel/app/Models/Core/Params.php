<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

use App\Models\Core\Groups;
use App\Models\Core\Objects;

class Params extends Model
{	
	protected $connection= 'core';
	protected $table = 'params';

	protected $primaryKey = 'id';

	public $timestamps = false;

	public function groups()
	{
		return $this->belongsToMany(Groups::class, 'groups_params', 'group_id', 'param_id');
	}

	public function objects()
	{
		return $this->belongsToMany(Objects::class, 'objects_params', 'object_id', 'param_id');
	}

	public function searchByName($text)
	{
		return $this->where('name','LIKE','%'.mb_strtolower($text).'%');
	}
}