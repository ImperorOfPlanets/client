<table class='table table-bordered text-center'>
	<thead>
		<th>
			Название
		</th>
		<th>
			Кнопки
		</th>
	</thead>
	<tbody>
		@foreach($settings as $setting)
		<tr>
			<td>{{$setting->propertys->where('id',1)->first()->pivot->value ?? 'Без названия'}}</td>
			<td>
				<a class="form-control btn btn-success" href="/management/settings/site/{{$setting->id}}/edit">
					Настроить
				</a>
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