<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SettingsController extends Controller
{
	//Группа записей
	public $groupID = 5;
	//Переменная для группы
	public $group = null;

	public function index()
	{
		return view('user.settings.index');
	}
}