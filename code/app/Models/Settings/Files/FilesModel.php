<?php

namespace App\Models\Settings\Files;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FilesModel extends Model
{
    use HasFactory;

	protected $table = 'files';

	protected $primaryKey = 'guid';

	protected $fillable = array('name','ext','ip');

    protected $casts = [
		'guid' => 'string',
        'add_info' => 'array'
    ];
}