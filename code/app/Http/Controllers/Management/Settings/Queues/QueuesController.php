<?php
namespace App\Http\Controllers\Management\Settings\Queues;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QueuesController extends Controller
{
	public function index(Request $request)
	{
        if(isset($request->type))
        {
            $type = $request->type;
            if($type='failed')
            {
                $queues = DB::table('failed_jobs')->orderBy('id','desc')->get();
                return view('management.settings.queues.failed',[
                    'queues'=>$queues
                ]);
            }
        }
        else
        {
            $queues = DB::table('jobs')->orderBy('id','desc')->get();
            return view('management.settings.queues.jobs',[
                'queues'=>$queues
            ]);    
        }
	}

    public function destroy(Request $request,$id)
	{
        if(isset($request->type))
        {
            if($request->type=='failed')
            {
                $queue = DB::table('failed_jobs')->where('id',$id)->delete();
                return back();
            }
        }
        else
        {
            $queue = DB::table('jobs')->where('id',$id)->delete();
            return back();
        }
	}
}