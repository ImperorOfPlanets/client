<?php
namespace App\Models\Settings\Processes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcessesModel extends Model
{
	use SoftDeletes;

	protected $table = 'processes';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected function asJson($value,$flag=0)
    {
		return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}