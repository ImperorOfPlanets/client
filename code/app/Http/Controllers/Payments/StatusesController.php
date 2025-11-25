<?php
namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;

use App\Models\Payments\StatusesModel;

class StatusesController extends Controller
{
    public function index()
    {
        $statuses = StatusesModel::paginate(10);
        //Вернуть статусы в JSON
    }
}