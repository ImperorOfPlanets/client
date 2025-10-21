<?php
namespace App\Http\Controllers\Management\Payments;

use App\Http\Controllers\Controller;

use App\Models\Payments\StatusesModel;

class StatusesController extends Controller
{
    public function index()
    {
        $statuses = StatusesModel::paginate(10);
        return view('management.payments.statuses.index',[
			'statuses'=>$statuses
		]);
    }
}