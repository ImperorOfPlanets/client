@extends('template.template',[
	'title'=>'Roles'
])
@section('content')
    @push('sidebar') @include('management.sidebar') @endpush
	<table class='table w-full border-collapse border border-slate-400 align-middle text-center mt-2'>
		<thead>
			<tr>
				<td class="border border-slate-300">Название</td>
				<td class="border border-slate-300"></td>
			</tr>
		</thead>
		<tbody>
			@foreach($roles as $role)
				<tr>
					<td class="border border-slate-300">{{$role->propertyByID(1)->pivot->value ?? 'Без названия'}}</td>
					<td class="border border-slate-300">
						<a
							href="/management/users/roles/{{$role->id}}/edit"
							class="btn btn-success form-control"
						>
							Изменить список
						</a>
					</td>
				</tr>
			@endforeach
		</tbody>
	</table>
	<script>
	$(document).ready(function(){
		console.log('>>>>>>>>>>>>>>>>>> DOCUMENT READY - management.users.roles.index <<<<<<<<<<<<<<<<<<<<<');
	});
	</script>
@endsection