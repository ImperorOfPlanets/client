@extends('template.template',[
	'title'=>'Roles'
])
@section('content')
    @push('sidebar') @include('management.sidebar') @endpush
	<div class="text-center container-fluid fs-3">
		{{$object->propertyById(1)->pivot->value}}
	</div>
	<hr />
	<div class='p-2'>
		<label>Введите ID с сайта MyIdOn.Site</label>
		<input type="text" class='form-control mb-2' name='user' placeholder='Введите ID'>
		<button class='form-control btn btn-success' data-action="add">Добавить</button>
	</div>
	<table class='table w-full border-collapse border border-slate-400 align-middle text-center mt-2'>
		<thead>
			<tr>
				<td class="border border-slate-300">ID ПОЛЬЗОВАТЕЛЯ</td>
				<td class="border border-slate-300">КНОПКА</td>
			</tr>
		</thead>
		<tbody>
			@foreach($users as $user)
				<tr>
					<td class="border border-slate-300">{{$user}}</td>
					<td class="border border-slate-300">
						<button
							data-action="delete"
							data-user-id="{{$user}}"
							class="btn btn-primary"
							type="button"
						>
								Удалить
						</button>
					</td>
				</tr>
			@endforeach
		</tbody>
	</table>
	<script>
	$(document).ready(function(){
		console.log('>>>>>>>>>>>>>>>>>> DOCUMENT READY - management.users.roles.edit <<<<<<<<<<<<<<<<<<<<<');
		//Кнопка добавить
		$('body').on('click','[data-action]',function(e){
			var target = e.relatedTarget;
			action = $(target).attr('data-action');
			var fd = new FormData();
			fd.append('command','action');
			if(action=='delete')
			{
				fd.append('user_id',$(target).attr('data-user-id'));
			}
			if(action=='add')
			{
				var id = $('[name=user]').val();
				if(typeof id === 'number' && !isNaN(id))
				{
					fd.append('user_id',$('[name=user]').val());
				}
				else
				{
					event.stopPropagation();
				}
			}
			fd.append('_method','put');
			$.ajax({
				url:"{{$urlForUpdate}}",
				type: 'post',
				data: fd,
				dataType:'json'
			});
		});
	});
	</script>
@endsection