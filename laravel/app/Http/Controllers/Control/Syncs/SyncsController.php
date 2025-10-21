<?php
namespace App\Http\Controllers\Control\Syncs;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncsController extends Controller
{
	public function index(Request $request)
	{
        if(isset($request->type))
        {
            if($request->type=='failed')
            {
                $jobs = DB::connection('core')->table('failed_jobs')->get();
                return view('control.syncs.failed',[
                    'jobs'=>$jobs
                ]);   
            }
        }
        else
        {
            $jobs = DB::connection('core')->table('jobs')->get();
            return view('control.syncs.index',[
                'jobs'=>$jobs
            ]);    
        }
	}

    public function destroy(Request $request,$id)
	{
        if(isset($request->type))
        {
            if($request->type=='failed')
            {
                $jobs = DB::connection('core')->table('failed_jobs')->where('id',$id)->delete();
                return back();
            }
        }
        else
        {
            $jobs = DB::connection('core')->table('jobs')->where('id',$id)->delete();
            return back();
        }
	}
}