@extends('template.template',[
	'title'=>'Welcome'
])
@section('content')
@push('sidebar') @include('management.sidebar') @endpush
<button data-action="addJob" class='form-control btn btn-success mb-1'>Обновить все слова</button>
<table class="table table-bordered table-hover text-center table-responsive">
	<thead>
		<tr>
			<td>Ключ</td>
			<td>Кнопки</td>
		</tr>
	</thead>
	<tbody>
	@foreach($keywords as $key)
		<tr>
			<td class="align-middle">
				{{$key->keyword}}
			</td>
			<td data-params='{{$key->params}}'>
				<button class='btn btn-success'>Показать парамметры</button>
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
			var fd = new FormData();
			fd.append('command',action);
			if(action=='addJob')
			{
				$.ajax({
					url:"/management/settings/keywords",
					type: 'post',
					data: fd,
					dataType:'json'
				});
			}
		});
	});
</script>
@endsection