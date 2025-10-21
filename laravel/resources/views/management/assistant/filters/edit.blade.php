@extends('template.template',[
    'title'=>'Edit filter',
])
@section('content')

@push('sidebar') @include('management.sidebar') @endpush

<div class="p-2">
    <div class="row">
        <div class="col-6">
            Название
            <input type='text' name='name' data-focusout='change-property' data-field-id="1" class='form-control' value="{{$filter->propertyByID(1)->pivot->value ?? 'Без названия'}}">
        </div>
        <div class="col-6">
            <!-- ФОРМАТ ФИЛЬТРА -->
            <div class='form_selectet p-2'>
                Выберите тип фильтра
                @php
                    $p107 = $filter->propertyById(107);
                @endphp
                <select class="form-select type-selecter" name='type'>
                    <option value='none' disabled @if(is_null($p107)) selected @endif>
                        Выберите тип
                    </option>
                    <option value="prompt" @if(!is_null($p107) && $p107->pivot->value=='prompt') selected @endif>
                        Текстовый промт
                    </option>
                    <option value="handler" @if(!is_null($p107) && $p107->pivot->value=="handler") selected @endif>
                        Обработчик
                    </option>
                </select>
            </div>
        </div>
    </div>
    
    <div class="form-inserter mt-3">
        <!-- Сюда будет вставляться форма в зависимости от выбранного типа -->
    </div>
    
    <div class="mt-3">
        Краткое описание
        <textarea name='desc' data-focusout='change-property' data-field-id="109" class='form-control'>{{$filter->propertyByID(109)->pivot->value ?? ''}}</textarea>
    </div>
</div>

<!-- СКРЫТЫЕ ФОРМЫ ДЛЯ ВСТАВКИ -->
<div class='form-ins d-none'>
    <div type='prompt'>
        Текст промта
        <textarea name='text' data-focusout='change-property' data-field-id="108" class='form-control' rows="5">{{$filter->propertyByID(108)->pivot->value ?? ''}}</textarea>
    </div>
    <div type='handler'>
        Обработчик фильтра
        <input type="text" name="handler" data-focusout="change-property" data-field-id="108" class="form-control" 
               value="{{$filter->propertyByID(108)->pivot->value ?? ''}}" 
               placeholder="App\Handlers\Filters\ExampleHandler@handle">
        <small class="text-muted">Формат: ClassName@methodName</small>
    </div>
</div>

<script>
    // Функция вставки соответствующей формы
    function insertForm() {
        $('.form-inserter').empty();
        var selectedType = $(".type-selecter").val();
        var formToInsert = $('.form-ins [type=' + selectedType + ']').clone();
        $('.form-inserter').append(formToInsert);
    }

    $(document).ready(function(){
        // Инициализация формы при загрузке
        if($(".type-selecter").val() != 'none') {
            insertForm();
        }

        // Обработчик изменения типа
        $(".type-selecter").change(function(){
            if($(this).val() != 'none') {
                insertForm();
                
                // Сохраняем тип фильтра
                var fd = new FormData();
                fd.append('command','change-property');
                fd.append('property_id',107);
                fd.append('value',$(this).val());
                fd.append('_method','put');
                
                $.ajax({
                    url:"{{$urlForUpdate}}",
                    type: 'post',
                    data: fd,
                    dataType:'json'
                });
            }
        });

        // Обработчик потери фокуса для динамически добавленных полей
        $('body').on('focusout','[data-focusout]',function(e){
            var fd = new FormData();
            fd.append('command',$(this).attr('data-focusout'));
            fd.append('property_id',$(this).attr('data-field-id'));
            fd.append('value',$(this).val());
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