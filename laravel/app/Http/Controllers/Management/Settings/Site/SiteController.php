<?php
namespace App\Http\Controllers\Management\Settings\Site;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Models\Settings\Site\SettingsModel;

use Illuminate\Support\Facades\Route;
use App\Helpers\Editor\Editor;

class SiteController extends Controller
{
	public function index()
	{
		return view('management.settings.index');
	}

    public function edit($id)
	{
		//Объект редактирования
		$object = SettingsModel::find($id);
		$editor = new Editor($object);
		$editor->getAllPropertys();
		//dd($editor);
		return view('management.editor',[
			'object'=>$object,
			'editor'=>$editor,
			'urlForUpdate'=>$this->getUrlForUpdate()
		]);
	}
	public function getUrlForUpdate()
	{
		$current = Route::getCurrentRoute();
		$url = '';
		foreach($current->parameterNames as $param)
		{
			$url = $url.$param.'s';
			if(isset($current->parameters[$param]))
			{
				$url = $url.'/'.$current->parameters[$param];
			}
		}
		return '/'.Route::getCurrentRoute()->getPrefix().$url;
	}
}