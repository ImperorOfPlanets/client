
<table class='table table-bordered text-center'>
	<thead>
		<th>
			Социальная сеть
		</th>
		<th>
			Название
		</th>
		<th>
			Состояние
		</th>
		<th>
			Настроить
		</th>
	</thead>
	<tbody>
		@foreach($socials as $social)
			<tr>
				<td class="align-middle">
					<img src="{{$social->propertys->where('id',11)->first()->pivot->value ?? '#'}}" width="50">
				</td>
				<td class="align-middle">
					{{$social->propertys->where('id',1)->first()->pivot->value ?? '#'}}
				</td>
				<td class="align-middle">
					@if($social['install'])
						@php
							$value = $social->propertyById(116)->pivot->value ?? null;
						@endphp
						<input type="checkbox" name="on" data-object-id="{{$social->id}}" data-action='116' @if(filter_var($value,FILTER_VALIDATE_BOOLEAN)) checked @endif />
					@else
						<a class="form-control btn btn-danger" href="/management/assistant/settings/{{$social->id}}/">
							Требуется установка. Обратитесь в службу поддержки
						</a>
					@endif
				</td>
				<td class="align-middle">
					<a class="form-control btn btn-success" href="/management/assistant/settings/{{$social->id}}/edit">Настройка</a>
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
			url:"/management/assistant/settings/"+obj,
			type: 'post',
			data: fd,
			dataType:'json',
			contentType: false,
			processData: false
		});
	});
});
</script>