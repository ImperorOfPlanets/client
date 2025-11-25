<?php
namespace App\Http\Controllers\Management\Settings\Ips;

use App\Http\Controllers\Controller;

use App\Models\Ips\IpsModel;
use Illuminate\Http\Request;

use App\Models\Propertys;

class IpsController extends Controller
{

	public function index()
	{
        $ips = IpsModel::all();
        return view('management.settings.ips.index',['ips'=>$ips]);
	}
}