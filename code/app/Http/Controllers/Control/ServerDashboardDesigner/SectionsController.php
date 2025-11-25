<?php

namespace App\Http\Controllers\Control\ServerDashboardDesigner;

use App\Http\Controllers\Controller;

use App\Models\Core\Objects;
use App\Models\Core\Groups;

class SectionsController extends Controller
{
	public $object = null;
	public $group = null;
	public $groupID = 30;

    public function __construct()
    {
    }

	public function index()
	{
		$this->group = Groups::find($this->groupID);
		return view('control.serverdashboarddesigner.sections.index',['objects'=>$this->group->objects]);
	}
}