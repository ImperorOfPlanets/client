<?php
namespace App\Models\Assistant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KeywordsModel extends Model
{
	protected $table = 'keywords';

	protected $primaryKey = 'key';

	public $timestamps = false;

	protected function asJson($value,$flag=0)
    {
		return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}