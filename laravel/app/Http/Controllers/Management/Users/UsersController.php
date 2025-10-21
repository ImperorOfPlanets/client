<?php
namespace App\Http\Controllers\Management\Users;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

class UsersController extends Controller
{
	public function index()
	{
		return view('management.users.users.index');
	}
}