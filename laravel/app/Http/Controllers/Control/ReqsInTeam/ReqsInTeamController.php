<?php

namespace App\Http\Controllers\Control\ReqsInTeam;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Bus;
use Illuminate\Http\Request;

use App\Models\Core\ReqsInTeam;

use App\Jobs\ForControlProjects\CreateAccounts\CreateEmail;
use App\Jobs\ForControlProjects\CreateAccounts\CreateVPN;
use App\Jobs\ForControlProjects\CreateAccounts\CreateGitFlic;

class ReqsInTeamController extends Controller
{
    public $reqsinteam = null;

	public function index()
	{
		$this->reqsinteam = ReqsInTeam::paginate(10);
		return view('control.reqsinteam.index',[
            'reqsinteam'=>$this->reqsinteam
        ]);
	}

    public function create()
	{
		return view('control.reqsinteam.create');
	}

	public function destroy(Request $request,$id)
	{
		$req = ReqsInTeam::find($id);
		$req->delete();
		return redirect('/control/reqsinteam');
	}

	public function store(Request $request)
	{
		$req = new ReqsInTeam;
		$params = [
			'login'=>$request->login,
			'password'=>$request->password
		];

		$jobs =[];

		if($request->has('email'))
		{
			$params['email']=true;
			$jobEmail = new createEmail([
				'domain'=>'myidon.site',
				'login'=>$request->login,
				'password'=>$request->password
			]);
			$jobs[] =$jobEmail;
		}

		if($request->has('vpn'))
		{
			$params['vpn']=true;
			$jobVPN = new CreateVPN([
				'login'=>$request->login,
				'password'=>$request->password
			]);
			$jobs[] =$jobVPN;
		}

		if($request->has('gitflic'))
		{
			$params['gitflic']=true;
			$jobGitFlic = new CreateGitFlic([
				'email'=>$request->login.'@myidon.site',
				'login'=>$request->login,
				'password'=>$request->password,
				'alias'=>$request->login
			]);
			$jobs[] =$jobGitFlic;
		}

		$req->params = json_encode($params);
		$req->save();
		if(count($jobs)>0){
			Bus::chain($jobs)->onQueue('default')->dispatch();
		}
		return redirect('/control/reqsinteam/create');
	}
}