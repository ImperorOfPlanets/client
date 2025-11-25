@extends('template.template',[
	'title'=>'Product'
])
@section('content')

@push('sidebar') @include('management.sidebar') @endpush
<form action="{{route('m.shop.products.update',['product'=>$product->id])}}" method='post'>
	@csrf
	@method('put')
	<div class='p-2'>
		Название
		<input type='text' name='name' class='form-control' value="{{$product->propertyByID(1)->pivot->value ?? 'Без названия'}}">
	</div>
	<div class='p-2'>
		<button class='btn btn-success btn-block'>Сохранить</button>
	</div>
</form>
<hr />
<table class='table w-full border-collapse border border-slate-400 align-middle text-center'>
	<thead>
		<tr>
			<td  class="border border-slate-300">
				ID Свойства
			</td>
			<td class="border border-slate-300">
				Название свойства
			</td>
			<td class="border border-slate-300">
				Описание
			</td>
			<td class="border border-slate-300">
				Значение
			</td>
			<td class="border border-slate-300">
				Управление
			</td>
		</tr>
	</thead>
	<tbody>
		@php
			$fields = $product->fields();
		@endphp
		@foreach($fields as $field)
		<tr data-field-id="{{$field->property_id}}">
			@php
				$property = App\Models\Propertys::find($field->property_id);
				$params = json_decode($field->params,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
			@endphp
			<td class="border border-slate-300">
				{{$field->property_id}}
			</td>
			<td class="border border-slate-300">
				{{$property->name ?? ''}}
			</td>
			<td class="border border-slate-300">
				{{$params['desc'] ?? ''}}
			</td>
			<td class="border border-slate-300">
				<input class="form-control" type="text" data-value value="{{$product->propertyByID($field->property_id)->pivot->value ?? ''}}" />
			</td>
			<td class="border border-slate-300">
				<input type="button" class="btn btn-success" data-action="save" value="Сохранить">
			</td>
		</tr>
		@endforeach
	</tbody>
</table>
<script>
	$(document).ready(function(){
		console.log('>>>>>>>>>>>>>>>>>> DOCUMENT READY - edit <<<<<<<<<<<<<<<<<<<<<');
		$('body').on('click','[data-action]',function(){
			var action = $(this).attr('data-action');
			if(action=='save')
			{
				var fieldID = $(this).closest('tr').attr('data-field-id');
				//console.log($(this).closest('tr').find('[data-field-id]'));
				var fd = new FormData();
				fd.append('command','change-property');
				fd.append('command','change-property');
				fd.append('property_id',fieldID);
				fd.append('value',$(this).closest('tr').find('[data-value]').val());
				fd.append('_method','put');
				$.ajax({
					url:"{{route('m.shop.products.update',['product'=>$product->id])}}",
					type: 'post',
					data: fd,
					dataType:'json'
				});
			}
		});
	});
</script>
@endsection