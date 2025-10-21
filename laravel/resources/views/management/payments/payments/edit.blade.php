@extends('template.template',[
	'title'=>'Платеж'
])
@section('content')
    @push('sidebar') @include('management.sidebar') @endpush
    <div class='p-2'>
        <label>Платежная система</label>
        <input class="form-control" type="text" value="{{\App\Models\Payments\ProvaidersModel::find($payment->propertyById(122)->pivot->value)->propertyById(1)->pivot->value}}" disabled="disbled" />
        <label>Сумма</label>
        <input class="form-control" type="text" value="{{$payment->propertyById(120)->pivot->value}}" disabled="disbled" />
        <label>Валюта</label>
        <input class="form-control" type="text" value="{{\App\Models\Payments\CurrencysModel::find($payment->propertyById(121)->pivot->value)->propertyById(1)->pivot->value}}" disabled="disbled" />
    </div>
@endsection
