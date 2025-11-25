@extends('template.template',[
	'title'=>'Логи'
])
@section('content')
@push('sidebar') @include('management.sidebar') @endpush
<div class="form-check">
	<input class="form-check-input" type="checkbox" value="" id="autoupdate">
	<label class="form-check-label" for="autoupdate">
		Автообновление каждые 5 секунды
	</label>
</div>
<a href="/management/settings/logs/set" class='btn btn-success w-100'>Изменить настройки логирования</a>

<!-- Фильтровать -->
<input type="text" class="form-control" placeholder="Текст фильтрации" name="search">
<br />
<!-- Основная таблица -->
<table id='main' class="table table-bordered table-hover text-center">
	<thead>
		<tr>
			<td>ID</td>
			<td>Текст</td>
            <td>Автор</td>
			<td>Дата</td>
		</tr>
	</thead>
	<tbody>
	@foreach($logs as $key=>$log)
		@php
			$class='';
			$bgP = 	$log->propertyById(107)->pivot->value ?? null;
			if(!is_null($bgP))
			{
				$class="class='bg-$bgP text-white'";
			}
		@endphp
		<tr>
			<td {!!$class!!}>
				{{$log->id}}
			</td>
			<td {!!$class!!} style="word-break:break-all;">
                {{$log->propertyById(2)->pivot->value ?? null}}
			</td>
			<td {!!$class!!}>
                {{$log->propertyById(12)->pivot->value ?? null}}
			</td>
			<td {!!$class!!}>
                {{$log->created_at}}
			</td>
		</tr>
	@endforeach
	</tbody>
</table>
{{ $logs->links('vendor.pagination.bootstrap-4') }}
<script>
	//Переменнная интервала автообновления
	var autoupdateInterval = null;
	// Функция для обновления данных в таблице
	function updateTable()
	{
		// Здесь должен быть ваш код для получения новых данных
		// Это пример, который просто меняет текст в ячейке с ID "status"
        $.ajax({
            url: '/management/settings/logs?autoupdate=true',
            method: 'GET',
			dataType: "json",
            success:function(response)
			{
                var dataTableBody = $('#main tbody');
                dataTableBody.empty(); // Очистить текущие данные
                $.each(response.data,function(number,item) {
					//console.log(number,item);
					var propertys = item.propertys;
                    //console.log(propertys);
					var content = propertys.findIndex(property => property.id === 2);
					var author = propertys.findIndex(property => property.id === 12);
					var type = propertys.findIndex(property => property.id === 107);
					//console.log(content,author,type);
					var rowHtml = '<tr>';
							//Фоны строк
					var classes = '';
					if(type==-1)
					{classes='text-black';}
					else{classes='bg-' + propertys[type]['pivot']['value'] + ' text-white';}
							//Дата
					const date = new Date(item.created_at);
					date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
							//Автор

                    rowHtml += '<td class="'+ classes + '">' + item.id + '</td>';
                    rowHtml += '<td class="'+ classes + '" style="word-break:break-all;">' + propertys[content]['pivot']['value'] + '</td>';
                    rowHtml += '<td class="'+ classes + '">' + propertys[author]['pivot']['value'] + '</td>';
                    rowHtml += '<td class="'+ classes + '">' + date.toLocaleString() + '</td>';
                    rowHtml += '</tr>';
                    dataTableBody.append(rowHtml);
					filter();
                });
            }
        });
	}
	//Проверка автообновления
	function checkAutoUpdate()
	{
		var autoUpdateCheckbox = document.getElementById('autoupdate');
		if (autoUpdateCheckbox.checked)
		{
			alert('Включаем автообновление');
			//console.log(window['autoupdateInterval']);
			window['autoupdateInterval'] = setInterval(updateTable,5000); // Обновляем таблицу каждые 5 секунд
		}
		else
		{
			//console.log('Отключаем');
			//console.log(window['autoupdateInterval']);
			clearInterval(window['autoupdateInterval']); // Останавливаем интервал, если чекбокс отключен
		}
	}
	//Функеция фильтрации таблицы
	function filter()
	{
		var value = $("[name=search]").val().toLowerCase();
		$("#main tr").filter(function() {
			$(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
		});
	}
	$(document).ready(function(){
		console.log('>>>>>>>>>>>>>>>>>> DOCUMENT READY - management/settings/logs/index <<<<<<<<<<<<<<<<<<<<<');

		$("[name=search]").on("keyup", function() {
			var value = $(this).val().toLowerCase();
			$("#main tr").filter(function() {
				$(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
			});
		});

		$('#autoupdate').on('change',function(){
			checkAutoUpdate();
		});
	});
</script>
@endsection