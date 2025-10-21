<?php

namespace App\Http\Controllers\Management\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Shop\BasketModel;

class BasketsController extends Controller
{
	public function index()
	{
		$baskets = BasketModel::paginate(15);
		return view('management.shop.baskets.index',['baskets'=>$baskets]);
	}

	public function edit($id)
	{
		$basket = BasketModel::find($id);
		return view('management.shop.baskets.edit',['basket'=>$basket]);
	}
}