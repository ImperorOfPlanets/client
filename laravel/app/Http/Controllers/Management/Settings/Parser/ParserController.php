<?php
namespace App\Http\Controllers\Management\Settings\Parser;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Models\Settings\Parsers\ParserModel;
use App\Models\Settings\Parsers\ParsActionsModel;

use App\Helpers\Logs\Logs as Logator;


use App\Helpers\Editor\Editor;

use Illuminate\Support\Facades\Route;

class ParserController extends Controller
{
    public $logator;

    public function index()
    {
        $parsers = ParserModel::paginate(15);
        return view('management.settings.parsers.index',[
            'parsers'=>$parsers
        ]);
    }

    public function create()
    {
        return view('management.settings.parsers.create');
    }

    public function store(Request $request)
    {
		$parser = new ParserModel;
		$parser->save();
		$parser->propertys()->attach(1,['value'=>$request->name]);

		$this->logator = new Logator;

		$this->logator->setAuthor('Management Parsers ParserController');
		$this->logator->setType('success');
		$this->logator->setText("Пользователь ".session()->get('user_id')." создал парсер - $parser->id");
		$this->logator->write();

		return redirect()->route('m.settings.parsers.edit', $parser->id);
    }

    public function edit(Request $request,$id)
    {
		$parser = ParserModel::find($id);
		$parsActions = ParsActionsModel::all();
		return view('management.settings.parsers.edit',[
			'parser'=>$parser,
			'parsActions' =>$parsActions
		]);
		/*
		$object  = ParserModel::find($id);
		$editor = new Editor($object);
		$editor->getAllPropertys();
		return view('management.editor',[
			'object'=>$object,
			'editor'=>$editor,
			'urlForUpdate'=>$this->getUrlForUpdate()
		]);*/
    }

    public function update(Request $request,$id)
    {
		$object = ParserModel::find($id);
		if($request->command == 'change-property')
		{
			$property = $object->propertyById($request->property_id);
			if(is_null($property))
			{
				$object->propertys()->attach($request->property_id,['value'=>$request->value]);
			}
			else
			{
				$property->pivot->value=$request->value;
				$property->pivot->save();
			}
		}
    }

    public function getUrlForUpdate()
	{
		$current = Route::getCurrentRoute();
		$url = '';
		foreach($current->parameterNames as $param)
		{
			$url = $url.'/'.$param.'s';
			if(isset($current->parameters[$param]))
			{
				$url = $url.'/'.$current->parameters[$param];
			}
		}
		return '/'.Route::getCurrentRoute()->getPrefix().$url;
	}

    public function show(Request $request,$id)
    {
        //Объект показа
		$object = ParserModel::find($id);
    }
}