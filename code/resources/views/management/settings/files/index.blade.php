@extends('template.template',[
	'title'=>'Welcome'
])
@section('content')
    @push('sidebar') @include('management.sidebar') @endpush
	<table class='table w-full border-collapse border border-slate-400 align-middle text-center mt-2'>
		<thead>
			<tr>
				<td class="border border-slate-300">GUID</td>
				<td class="border border-slate-300">Парамметры</td>
			</tr>
		</thead>
		<tbody>
			@foreach($files as $file)
			<tr>
				<td class="border border-slate-300">{!!$file->guid!!}</td>
				<td class="border border-slate-300"></td>
			</tr>
			@endforeach
		</tbody>
	</table>
@endsection