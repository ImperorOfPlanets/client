<?php
namespace App\Http\Controllers\Management\Settings\Keywords;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Models\Settings\Keywords\KeywordsModel;
use App\Models\Propertys;

use App\Jobs\Keywords\GenKeywords;

class KeywordsController extends Controller
{

	public function index()
	{
		try
		{
			$keywords = KeywordsModel::paginate(20);
		}
		catch(\Exception $e)
		{
			$keywords = [];
		}
		return view('management.settings.keywords.index',[
			'keywords'=>$keywords
		]);
	}

	public function store(Request $request)
	{
		if($request->command == 'addJob')
		{
			dispatch(new GenKeywords());
		}
	}
}