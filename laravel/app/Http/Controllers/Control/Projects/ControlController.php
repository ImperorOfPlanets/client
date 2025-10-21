<?php

namespace App\Http\Controllers\Control\Projects;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Models\Core\Objects;

use App\Helpers\Ssh;

class ControlController extends Controller
{
    public $object = null;
	public $group = null;
	public $groupID = 9;

    public function __construct()
    {
        //$this->object = Objects::find(request()->route()->parameter(array_key_first(request()->route()->parameters())));
        //$this->ssh = new Ssh(['object_id'=>$this->object->id]);
    }

	public function index()
	{
		return view('control.projects.control.index');
	}

    public function store(Request $request)
	{
        $this->object = Objects::find(request()->route()->parameter(array_key_first(request()->route()->parameters())));
		if($request->command=='npmrb')
        {
            $this->ssh = new Ssh(['object_id'=>$this->object->id]);
            $sshVITE = 'cd '.$this->object->propertyById(64)->pivot->value. ' && bash npm.sh';
            dd($this->ssh->runCommand($sshVITE));
        }
		if($request->command=='clearCache')
        {
			//Подключаемся по ssh
			$this->ssh = new Ssh(['object_id'=>$this->object->id]);
			//php 37
			$artisanOptimize = 'cd '.$this->object->propertyById(64)->pivot->value. ' && '.$this->object->propertyById(37)->pivot->value.' artisan optimize:clear';
			dd($this->ssh->runCommand($artisanOptimize));
		}

		if($request->command=='checkDB')
        {
			
		}
	}
}