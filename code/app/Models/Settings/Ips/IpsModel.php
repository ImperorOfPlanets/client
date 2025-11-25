<?php

namespace App\Models\Settings\Ips;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Propertys;

use Illuminate\Support\Facades\DB;

class IpsModel extends Model
{
	protected $table = "ips";

	protected $primaryKey = "id";
	public $timestamps = false;

}