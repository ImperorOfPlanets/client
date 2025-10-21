<?php

namespace App\Http\Controllers\Management\Settings\Files;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Models\Settings\Files\FilesModel;

class FilesController extends Controller{

	public $prefix_table = 'table';

	public function index(){
		$files = FilesModel::paginate(15);
		return view('management.settings.files.index',[
			'files'=>$files
		]);
	}
}