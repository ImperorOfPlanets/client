<div class='propertys-list'>

	<div class="card d-none" data-property="102">
		<div class="card-header">
			Объекты
		</div>
		<div class="card-body">
			<input type="text" name='name' class='form-control' value="{{$doc->propertyById(102)->pivot->value ?? '[]'}}" />
		</div>
		<div class="card-footer">
			<button data-action="saveProperty">Сохранить</button>
		</div>
	</div>

	<div class="card" data-property="1">
		<div class="card-header">
			Название
		</div>
		<div class="card-body">
			<input type="text" name='name' class='form-control' value="{{$doc->propertyById(1)->pivot->value ?? 'Без названия'}}" />
		</div>
		<div class="card-footer">
			<button data-action="saveProperty">Сохранить</button>
		</div>
	</div>

	<div class="card" data-property="103">
		<div class="card-header">
			Фон
		</div>
		<div class="card-body">
			<input type="text" name='background' class='form-control' value="{{$doc->propertyById(103)->pivot->value ?? 'Отсуствует'}}" />
		</div>
		<div class="card-footer">
			<button data-action="saveProperty">Сохранить</button>
		</div>
	</div>

</div>
<script>
$(document).ready(function(){
	$('.propertys-list').on('click','button[data-action]',function(){
		var action = $(this).attr('data-action');
		if(action=="saveProperty")
		{
			console.log($(this).parents('.card'));
			var fd = new FormData();
			fd.append('command',action);
			fd.append('property_id',$(this).parents('.card').attr('data-property'));
			var value = $(this).parents('.card').find('.card-body input')[0].value;
			fd.append('value',value);
			fd.append('_method','put');
			$.ajax({
				url:"{{route('docs.update',$doc->id)}}",
				type: 'post',
				data: fd,
				dataType:'json'
			});
		}
	});
});
</script>