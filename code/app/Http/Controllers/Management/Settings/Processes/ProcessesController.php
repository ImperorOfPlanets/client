<?php
namespace App\Http\Controllers\Management\Settings\Processes;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Models\Settings\Processes\ProcessesModel;

class ProcessesController extends Controller
{
	public function index()
	{
		$processes = ProcessesModel::whereNull('deleted_at')->get();
		return view('management.settings.processes.index',[
			'processes'=>$processes
		]);
	}

	public function store(Request $request)
	{
		if($request->command == 'addJob')
		{
			dispatch(new GenKeywords());
		}
		if($request->command == 'killProcess')
		{
			exec("kill $request->pid", $output, $returnVar);
			ProcessesModel::where('pid',$request->pid)->delete();
		}
	}
}