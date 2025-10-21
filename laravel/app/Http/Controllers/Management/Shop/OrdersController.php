<?php

namespace App\Http\Controllers\Management\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Shop\OrdersModel;

class OrdersController extends Controller
{
	public function index()
	{
		return view('management.shop.orders.index');
	}

	public function create()
	{
		return view('management.shop.orders.create');
	}

	public function store(Request $request)
	{
		$order = new OrderModel;
		$order->save();
		return redirect()->route('orders.edit', $product->id);
	}
}