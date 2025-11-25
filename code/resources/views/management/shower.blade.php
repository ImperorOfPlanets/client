@extends('template.template',[
	'title'=>'Settings',
])
@push('sidebar') @include('management.sidebar') @endpush
@section('content')
<table class='table w-full border-collapse border border-slate-400 align-middle text-center'>
	<thead>
		<tr>
			<td  class="border border-slate-300">
				ID Свойства
			</td>
			<td class="border border-slate-300">
				Название свойства
			</td>
			<td class="border border-slate-300">
				Описание
			</td>
			<td class="border border-slate-300">
				Значение
			</td>
		</tr>
	</thead>
	<tbody>
		@foreach($forShow as $field)
		<tr data-field-id="{{$field->property_id}}">

			<td class="border border-slate-300">
				{{$field->id}}
			</td>
			<td class="border border-slate-300">
				{{$field->name ?? ''}}
			</td>
			<td class="border border-slate-300">
				{{$field->desc ?? ''}}
			</td>
			<td class="border border-slate-300">
				<input class="form-control" type="text" disabled value="{{$field->pivot->value ?? null}}"/>
			</td>
		</tr>
		@endforeach
	</tbody>
</table>
@endsection

