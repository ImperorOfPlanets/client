<?php

namespace App\Http\Controllers\Control\ServerDashboardDesigner;

use App\Http\Controllers\Controller;

use App\Models\Core\Objects;
use App\Models\Core\Groups;

use Illuminate\Http\Request;

class ServersController extends Controller
{
	public $object = null;
	public $group = null;
	public $groupID = 29;

    public function __construct()
    {
    }

	public function index()
	{
		$this->group = Groups::find($this->groupID);
		return view('control.serverdashboarddesigner.servers.index',['objects'=>$this->group->objects]);
	}

	public function store(Request $request)
	{
		$server = new Objects;
		$server->save();
		$server->groups()->attach(29);
		$server->propertys()->attach(1, ['value' => $request->name]);
		return response()->json(['refresh' => 1],200,[],JSON_UNESCAPED_UNICODE);
	}
}