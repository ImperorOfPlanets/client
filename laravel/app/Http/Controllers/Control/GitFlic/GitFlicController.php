<?php

namespace App\Http\Controllers\Control\GitFlic;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

use App\Models\Core\Objects;

use App\Jobs\ForControlProjects\checkNewCommits;

class GitFlicController extends Controller
{

	public function index(Request $request)
	{
		return view('control.gitflic.index',[
            'object' => Objects::find(2)
        ]);
	}

	public function show(Request $request,$id)
	{
        if($id=='redirect')
        {
            $object = Objects::find(2);
            $code = $request->code;
            $url = "http://".$object->propertyById(9)->pivot->value."/api/token/access?code=$code";
            
            $response = Http::get($url);
            
            if ($response->successful()) {
                $data = $response->json();
                // Check if the pivot record exists
				if($object->propertyById(45)===null)
				{$object->propertys()->attach(45, ['value' => $data['accessToken']]);}
				else
				{$object->propertys()->updateExistingPivot(45,['value'=>$data['accessToken']]);}
                return redirect()->to('/control/gitflic');
            } else {
                // Handle error
            }
            //Теперь вы можете использовать API, вставляя полученный токен в headers нашего запроса:
            //Authorization: token <accessToken>
        }
        elseif($id=='addcheck')
        {
            dispatch(new checkNewCommits());
            return redirect()->to('/control/gitflic');
        }
	}
}