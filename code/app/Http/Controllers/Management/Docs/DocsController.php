<?php

namespace App\Http\Controllers\Management\Docs;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Docs\DocsModel;

class DocsController extends Controller
{
	public function index()
	{
		$docs = DocsModel::paginate(15);
		return view('management.docs.index',[
			'docs'=>$docs
		]);
	}

	public function create()
	{
		return view('management.docs.create');
	}

	public function store(Request $request)
	{
		$doc = new DocsModel;
		$doc->save();
		return redirect()->route('docs.edit', $doc->id);
	}

	public function edit(Request $request,$id)
	{
		$doc = DocsModel::find($id);
		return view('management.docs.edit',[
			'doc'=>$doc
		]);
	}

	public function update(Request $request,$id)
	{
		$doc = DocsModel::find($id);
		if($request->command == 'saveProperty')
		{
			$property = $doc->propertyById($request->property_id);
			if(is_null($property))
			{
				$doc->propertys()->attach($request->property_id,['value'=>$request->value]);
			}
			else
			{
				$property->pivot->value=$request->value;
				$property->pivot->save();
				//$doc->propertys()->attach($request->property_id,['value'=>$request->value]);
			}
		}
	}

}