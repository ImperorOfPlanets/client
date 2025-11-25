<?php

namespace App\Http\Controllers\Management\Payments;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Payments\CurrencysModel;

class CurrencysController extends Controller
{
	public function index()
	{
		$currencys = CurrencysModel::paginate(10);
		return view('management.payments.currencys.index',[
			'currencys'=>$currencys
		]);
	}
}