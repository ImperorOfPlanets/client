@extends('template.template',[
    'title'=>'Settings',
])

@push('sidebar') @include('management.sidebar') @endpush

@section('content')

<table class="table table-bordered table-hover align-middle text-center">
    <thead class="table-light">
        <tr>
            <th>ID Свойства</th>
            <th>Название свойства</th>
            <th>Описание</th>
            <th>Значение</th>
            <th>Ошибки</th>
        </tr>
    </thead>
    <tbody>
        @forelse($editor->fields as $field)
        <tr data-field-id="{{$field['property_id']}}">
            <td>{{$field['property_id']}}</td>
            <td>{{$field['name']}}</td>
            <td>{{$field['desc']}}</td>
            <td>
                @if($field['isEdit'])
                    <input type="text" 
                           value="{{ $field['value'] }}" 
                           data-focusout="change-property" 
                           data-field-id="{{ $field['property_id'] }}"
                           class="form-control form-control-sm">
                @else
                    @if($field['isShow'])
                        {{ $field['value'] }}
                    @else
                        <span class="text-muted">Нет доступа</span>
                    @endif
                @endif
            </td>
            <td>
                @if(count($field['errors']) > 0 && in_array(20, $editor->roles))
                    <button class="btn btn-link text-danger p-0 error-toggle" data-field-id="{{ $field['property_id'] }}">
                        Показать ошибки
                    </button>
                    <div id="errors-{{ $field['property_id'] }}" class="d-none small text-danger mt-1">
                        @foreach($field['errors'] as $error)
                            {{ $error }}<br>
                        @endforeach
                    </div>
                @endif
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="5" class="text-muted">Нет доступных свойств для редактирования</td>
        </tr>
        @endforelse
    </tbody>
</table>

<script>
    $(document).ready(function(){

        $('.error-toggle').click(function(e) {
            e.preventDefault();
            const fieldId = $(this).data('field-id');
            const errorsDiv = $(`#errors-${fieldId}`);
            errorsDiv.toggleClass('d-none');
            
            // Меняем текст кнопки
            $(this).text(errorsDiv.hasClass('d-none') ? 'Показать ошибки' : 'Скрыть ошибки');
        });

        // Оригинальная логика сохранения
        $('body').on('focusout','[data-focusout]',function(e){
            const fd = new FormData();
            fd.append('command', $(this).attr('data-focusout'));
            fd.append('property_id', $(this).attr('data-field-id'));
            fd.append('value', $(this).val());
            fd.append('_method','put');
            
            $.ajax({
                url: "{{$urlForUpdate}}",
                type: 'post',
                data: fd,
                dataType: 'json'
            });
        });
    });
</script>
@endsection