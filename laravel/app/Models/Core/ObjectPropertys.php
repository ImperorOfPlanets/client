<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ObjectPropertys extends Pivot
{
	protected $connection= 'core';
	public $timestamps = false;
	public $incrementing = false;
	protected $primaryKey = null;
	protected $table = 'objects_propertys';
}