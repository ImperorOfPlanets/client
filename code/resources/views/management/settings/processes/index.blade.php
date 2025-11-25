@extends('template.template',[
	'title'=>'Welcome'
])
@section('content')
@push('sidebar') @include('management.sidebar') @endpush
<!-- Обновить процессы -->
<button data-action="updateProcesses" class='form-control btn btn-success mb-1'>Обновить все список всех процессов</button>
<br />
<!-- Фильтровать -->
<input type="text" class="form-control" placeholder="Текст фильтрации" name="search">
<br />
<!-- Основная таблица -->
<table id='main' class="table table-bordered table-hover text-center">
	<thead>
		<tr>
			<td>PID</td>
			<td>Командная строка</td>
			<td>Кнопки</td>
		</tr>
	</thead>
	<tbody>
	@foreach($processes as $keyP=>$process)
		<tr>
			<td class="align-middle">
				{{$process->pid}}
			</td>
			<td data-params='{{$process->params}}'>
				@php
					$data = json_decode($process->params);
				@endphp
				{{$data->command}}
			</td>
			<td>
				<button class='btn btn-danger' data-action="killProcess">Выключить процесс</button>
			</td>
		</tr>
	@endforeach
	</tbody>
</table>
<script>
	$(document).ready(function(){
		console.log('>>>>>>>>>>>>>>>>>> DOCUMENT READY - management/assistant/keywords/index <<<<<<<<<<<<<<<<<<<<<');

		$('body').on('click','[data-action]',function(){
			var action = $(this).attr('data-action');
			if(action=='killProcess')
			{
				var fd = new FormData();
				fd.append('command',action);
				fd.append('pid',$(this).closest('tr').children('td:first').text().trim());
				$.ajax({
					url:"/management/settings/processes",
					type: 'post',
					data: fd,
					dataType:'json'
				});
			}
			/*else if(action=='search')
			{
				//Получаем
				var search = $('[name=search]').val();
				console.log('search: '+search);
				// Получаем элемент таблицы по его ID
				var table = document.getElementById('main');
				console.log(table);
				// Фильтруем строки таблицы, чтобы оставить только те, которые содержат слово 'apple'
				var value = $(this).val().toLowerCase();
				$("#main tr").filter(function() {
					$(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
				});
			}*/
		});

		$("[name=search]").on("keyup", function() {
			var value = $(this).val().toLowerCase();
			$("#main tr").filter(function() {
				$(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
			});
		});

		$('#collapseFilter').on('show.bs.collapse',function(){
			//$('table#main').addClass('d-none');
		});

		$('#collapseFilter').on('hide.bs.collapse',function(){
			//$('table#main').removeClass('d-none');
		});
	});
</script>
@endsection