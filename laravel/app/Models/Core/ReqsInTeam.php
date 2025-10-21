<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class ReqsInTeam extends Model
{
	protected $connection= 'core';
	protected $table = 'reqsinteam';

	protected $primaryKey = 'id';

	public $timestamps = false;
}