<?php
namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Payments\ProvaidersModel;
use App\Models\Payments\CurrencysModel;

class ProvaidersController extends Controller
{

    //Список работающих платежных систем
    public $provaiders = null;

    //Ответ JSON
    public $result_json = [];



    public function index()
    {
        $provaiders = ProvaidersModel::all();
        foreach($provaiders as $provaider)
        {
            $currencysWorkJSON = $provaider->propertyById(124)->pivot->value;
            $currencysWork = json_decode($currencysWorkJSON);
            $currencysForRusult = [];
            foreach($currencysWork as $currency)
            {
                //inId - внутрений ID валюты у нас в системе
                //dd($currency);

                //outId - внешний ID валюты в системе

                //Название валюты
                $currencyName = CurrencysModel::find($currency)->propertyById(1)->pivot->value;
                //dd($currencyName);
                $currencysForRusult[] = [
                    'inId'=>$currency,
                    'outId'=>'',
                    'name'=>$currencyName
                ];
            }

            $this->result_json[] = 
                [
                    'id'=>$provaider->id,
                    'name'=>$provaider->propertyById(1)->pivot->value,
                    'currencys'=>$currencysForRusult
                ];
            //$provaider->propertyById(1)->pivot->value;
        }
        return response()->json($this->result_json);
    }
}