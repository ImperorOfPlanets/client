<?php

namespace App\Http\Controllers\Management\Organizations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OrganizationsController extends Controller
{
	public function index()
	{
		return view('organizations.index');
	}
}