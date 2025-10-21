<?php

namespace App\Http\Controllers\Management\Payments;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Payments\PaymentsModel;
use App\Models\Payments\StatusesModel;


class PaymentsController extends Controller
{
	public function index()
	{
		$payments = PaymentsModel::paginate(15);
		$statuses = StatusesModel::all();
		return view('management.payments.payments.index',[
			'payments'=>$payments,
			'statuses'=>$statuses
		]);
	}

	public function create()
	{
		return view('management.payments.payments.create');
	}

	public function store(Request $request)
	{
		$payment = new PaymentsModel;
		$payment->save();
		$payment->propertys()->attach(122,['value'=>$request->provaider]);
		$payment->propertys()->attach(120,['value'=>$request->summ]);
		$payment->propertys()->attach(121,['value'=>$request->currency]);
		return redirect('/management/payments/payments/'.$payment->id.'/edit');
	}

	public function edit(Request $request,$id)
	{
		$payment = PaymentsModel::find($id);
		return view('management.payments.payments.edit',[
			'payment'=>$payment
		]);
	}
}