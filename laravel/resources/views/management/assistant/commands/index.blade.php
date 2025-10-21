@extends('template.template',[
	'title'=>'Commands'
])
@section('content')
@push('sidebar') @include('management.sidebar') @endpush
	<a href="{{route('m.assistant.commands.create')}}">Создать команду</a>
	<table class='table table-bordered table-responsive text-center'>
	<thead>
		<td>ID</td>
		<td>Команда</td>
		<td>Описание</td>
		<td>Состояние</td>
		<td>Управление</td>
	</thead>
	<tbody>
		@foreach($commands as $command)
			<tr>
				<td>
					{{$command->id}}
				</td>
				<td>
					{{$command->propertyByID(1)->pivot->value ?? 'Без названия'}}
				</td>
				<td>
					@php
						$desc = $command->propertys->firstWhere('key','desc');
						if(is_null($desc))
						{
							echo "Описание отсутствует";
						}
						else
						{
							echo $desc->value;
						}
					@endphp
				</td>
				<td class='text-center'>
					@php
						$value = $command->propertyById(116)->pivot->value ?? null;
					@endphp
					<input type="checkbox" name="on" data-object-id="{{$command->id}}" data-action='116' @if(filter_var($value,FILTER_VALIDATE_BOOLEAN)) checked @endif />
				</td>
				<td>
					<a href="{{route('m.assistant.commands.edit',['command'=>$command->id])}}" >Редактировать</a>
				</td>
			</tr>
		@endforeach
	</tbody>
	</table>
	<script>
	$(document).ready(function(){
		console.log('>>>>>>>>>>>>>>>>>> DOCUMENT READY - control.core.groups.propertys <<<<<<<<<<<<<<<<<<<<<');

		//Галочка блока на показ
		$('table').on('click','[name=on]',function()
		{
			var obj = $(this).attr('data-object-id');
			var fd = new FormData();
			fd.append('command','change-property');
			fd.append('property_id',$(this).attr('data-action'));
			fd.append('value',$(this).prop('checked'));
			fd.append('_method','put');
			$.ajax({
				url:"/management/assistant/commands/"+obj,
				type: 'post',
				data: fd,
				dataType:'json',
				contentType: false,
				processData: false
			});
		});
	});
	</script>
	{{ $commands->links('vendor.pagination.bootstrap-4') }}
@endsection