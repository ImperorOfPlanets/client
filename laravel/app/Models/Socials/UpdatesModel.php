<?php
namespace App\Models\Socials;

use Illuminate\Database\Eloquent\Model;

class UpdatesModel extends Model
{
	protected $table = 'updates';

	protected $primaryKey = 'id';

    protected $casts = [
        'json' => 'array',
    ];

    protected $fillable = ['soc', 'json'];

	public $timestamps = false;

	protected function asJson($value,$flag=0)
    {
		return json_encode($value,JSON_UNESCAPED_UNICODE);
    }
}