@extends('template.template',[
    'title'=>'Create Command',
])
@section('content')

@push('sidebar') @include('management.sidebar') @endpush

<!-- НАЗВАНИЕ, КЛЮЧЕВЫЕ, ОПИСАНИЕ КОМАНДЫ -->
<div class="p-2">
    <div class='row'>
        <div class='col-6'>
            Название
            <input type='text' name='name' data-focusout='change-property' data-field-id="1" class='form-control' value="{{$command->propertyByID(1)->pivot->value ?? 'Без названия'}}">
        </div>
        <div class='col-6'>
            Ключевые слова
            <input type='text' name='keywords' data-json-editor="" data-focusout='change-property' data-field-id="8" class='form-control' value="{{$command->propertyByID(8)->pivot->value ?? ''}}">
        </div>
    </div>
    <hr />
    <div class='row'>
        <div class='col-6'>
            Описание
            <textarea class='w-100' rows='3' name="desc" data-focusout='change-property' data-field-id="109" class='form-control'>{{$command->propertyByID(109)->pivot->value ?? ''}}</textarea>
        </div>
        <div class='col-6'>
            <!-- ФОРМАТ КОМАНДЫ -->
            <div class='form_selectet p-2'>
                Выберите формат команды
                @php
                    $p107 =  $command->propertyById(107);
                @endphp
                <select class="form-select type-selecter" name='type'>
                    <option
                        value='none'
                        disabled
                        @if(is_null($p107))
                            selected 
                        @endif
                    >
                        Выберите тип
                    </option>
                    <option 
                        @if(!is_null($p107) && $p107->pivot->value=='answer')
                            selected
                        @endif
                        value="answer"
                    >
                        Обычный ответ
                    </option>
                    <option 
                        @if(!is_null($p107) && $p107->pivot->value=="controller")
                            selected
                        @endif
                        value="controller"
                    >
                        Контроллер
                    </option>
                </select>
            </div>
            <div class='form-inserter p-2'></div>
        </div>
    </div>
</div>

<div class='form-ins d-none'>
    <div type='answer'>
        Ответ
        <input type='text' name='answer' data-focusout='change-property' data-field-id="108" class='form-control' value="{{$command->propertyByID(108)->pivot->value ?? ''}}" />
    </div>
    <div type='controller'>
        Обработчик команды
        <input type="text" name="answer" data-focusout="change-property" data-field-id="108" class="form-control" value="{{$command->propertyByID(108)->pivot->value ?? ''}}" placeholder="App\Handlers\Commands\ExampleHandler@handle">
        <small class="text-muted">Текстовое поле для обработки команды</small>
    </div>
</div>
<hr />

<!-- ДОСТУПНОСТЬ КОМАНДЫ В ИНТЕРФЕЙСАХ -->
<table class='table table-bordered text-center'>
    <thead>
        <th>
            Изображение социальная сеть
        </th>
        <th>
            Название
        </th>
        <th>
            Доступность в социальной сети
        </th>
        <th>
            Пользователям
        </th>
        <th>
            Группам
        </th>
    </thead>
    <tbody>
@foreach($socials as $social)
    <tr class='social' data-social-id="{{$social->id}}">
        <td class="align-middle">
            <img src="{{$social->propertys->where('id',11)->first()->pivot->value ?? '#'}}" width="50">
        </td>
        <td class="align-middle">
            {{$social->propertys->where('id',1)->first()->pivot->value ?? '#'}}
        </td>
        <td class="align-middle">
            @php
                $value = $social->propertyById(116)->pivot->value ?? null;
            @endphp
            <input type="checkbox" name="on" data-change-access />
        </td>
        <td class="align-middle">
            @php
                $p117 =  $command->propertyById(117);
            @endphp
            <select for="users" name="forUser" class="form-control">
                <option 
                    @if(!is_null($p117) && $p117->pivot->value=='anybody')
                        selected
                    @endif
                    value="anybody"
                >
                     Никому
                </option>
                <option 
                    @if(!is_null($p117) && $p117->pivot->value=='all')
                        selected
                    @endif
                    value="all"
                >
                    Всем пользователям
                </option>
                <option 
                    @if(!is_null($p117) && $p117->pivot->value=='define')
                        selected
                    @endif
                    value="define"
                >
                    Определенным пользователям
                </option>
            </select>
            <div for="users" class='form-control mt-2' style="display:none">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>
                                ID
                            </th>
                            <th width='20%'></th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
                <button class='form-control btn btn-success'  data-bs-toggle="modal" data-bs-target="#addModal">Добавить</button>
            </div>
        </td>
        <td class="align-middle">
            @php
                $p118 =  $command->propertyById(118);
            @endphp
            <select for="groups" name="forGroups" class="form-control">
                <option 
                    @if(!is_null($p118) && $p118->pivot->value=='anybody')
                        selected
                    @endif
                    value="anybody"
                >
                     Никому
                </option>
                <option 
                    @if(!is_null($p118) && $p118->pivot->value=='all')
                        selected
                    @endif
                    value="all"
                >
                    Всем группам
                </option>
                <option 
                    @if(!is_null($p118) && $p118->pivot->value=='define')
                        selected
                    @endif
                    value="define"
                >
                    Определенным группам
                </option>
            </select>
            <div for="groups" class='form-control mt-2' style="display:none">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>
                                ID
                            </th>
                            <th width='20%'>1</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
                <button class='form-control btn btn-success' data-bs-toggle="modal" data-bs-target="#addModal">Добавить</button>
            </div>
        </td>
    </tr>
@endforeach
    </tbody>
</table>

<!-- Модальное окно добавить ID -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="addModalLabel"></h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control" />
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-primary" data-action-add>Добавить</button>
            </div>
        </div>
    </div>
</div>

@php
    //Получаем доступность в социальных сетях
    $p119 = $command->propertyById(119)->pivot->value ?? 'null';
@endphp

<script>
    //Объект прав
    var accessJSON = {!!$p119!!};
    if(accessJSON==null)
    {
        accessJSON={};
    }

    //Значение обновления доступов
    var updateALL = false;

    //Функция считывания
    function updateTable()
    {
        console.clear();
        updateALL = true;
        //Если не ноль
        if(accessJSON!=null)
        {
            console.log('Значение JSON');
            console.log(accessJSON);
            for (key in accessJSON){
                console.log(key);
                var socialId = key;
                //Находим строку в таблице
                var tr = $('tr[data-social-id="'+socialId+'"]');

                //Проверяем включеность
                var socialOn = false;

                if(accessJSON[socialId].on==true)
                {
                    console.log('Социальная сеть включена устанавливаем галку');
                    $(tr).find('input[name=on]').prop('checked', true);
                }

                if(accessJSON[socialId].users!=undefined)
                {
                    console.log('Пользователи');
                    //Меняемс значение select
                    if(accessJSON[socialId].users.access!=undefined)
                    {
                        console.log('Пользовательский access: '+accessJSON[socialId].users.access);
                        //console.log(v[socialId].users.access);
                        //console.log($(tr).find('select[for=users]'));
                        $(tr).find('select[for=users]').val(accessJSON[socialId].users.access).change();
                        $(tr).find('select[for=users]').trigger('change');
                    }

                    //Добавляем пользователей в таблицу
                    if(accessJSON[socialId].users.define!=undefined)
                    {
                        var DivIDS = $(tr).find('div[for=users');
                        var table = $(DivIDS).find('table tbody');
                        $(accessJSON[socialId].users.define).each(function(k,v){
                            console.log(k);
                            console.log(v);    
                            var trAppend = $('<tr class="align-middle"><td data-id>'+v+'</td><td><button class="btn btn-danger">X</button></td></tr>');
                            $(table).append(trAppend);
                        });
                    }
                }

                if(accessJSON[socialId].groups!=undefined)
                {
                    console.log('Группы');
                    //Меняемс значение select
                    if(accessJSON[socialId].groups.access!=undefined)
                    {
                        console.log('Групповой access: '+accessJSON[socialId].groups.access);
                        //console.log($(tr).find('select[for=groups]'));
                        $(tr).find('select[for=groups]').val(accessJSON[socialId].groups.access).change();
                        //$(tr).find('select[for=groups]').trigger('change');
                    }

                    //Добавляем пользователей в таблицу
                    if(accessJSON[socialId].groups.define!=undefined)
                    {
                        var DivIDS = $(tr).find('div[for=groups');
                        var table = $(DivIDS).find('table tbody');
                        $(accessJSON[socialId].groups.define).each(function(k,v){
                            console.log(k);
                            console.log(v);    
                            var trAppend = $('<tr class="align-middle"><td data-id>'+v+'</td><td><button class="btn btn-danger">X</button></td></tr>');
                            $(table).append(trAppend);
                        });
                    }
                }
            };
        }
        else
        {
            console.log('Значение JSON - ПУСТОЕ');
        }
        updateALL = false;
    }

    //Отправить новые значения доступов
    function sendNewAccess()
    {
        if(updateALL)
        {
            return true;
        }
        var fd = new FormData();
        fd.append('command','change-property');
        fd.append('property_id','119');
        var valInJSON = JSON.stringify(accessJSON);
        fd.append('value',valInJSON);
        fd.append('_method','put');
        $.ajax({
            url:"{{$urlForUpdate}}",
            type: 'post',
            data: fd,
            dataType:'json'
        });
    }

    //Пройти по таблице и получить значения
    function getValuesUsersOrGroups(tr)
    {
        console.log('>>>getValuesUsersOrGroups<<<');
        console.log(tr);
        var socialId = $(tr).data('social-id');

        //Получаем доступ к пользователям
        var acessForUsers = $(tr).find('select[for=users]');
        console.log('Элемент пользователей');
        console.log(acessForUsers);

        //Получаем значение
        var valueForUsers = $(acessForUsers).val();
        console.log('Значение пользователей');
        console.log(valueForUsers);

        if(valueForUsers == 'all' || valueForUsers == 'anybody')
        {
            if(accessJSON[socialId].users==undefined)
            {
                accessJSON[socialId].users = {};
            }
            
            accessJSON[socialId].users.access = valueForUsers;
        }
        else if(valueForUsers == 'define')
        {
            if(accessJSON[socialId].users==undefined)
            {
                accessJSON[socialId].users = {};
            }
            accessJSON[socialId].users.access = 'define';

            if(accessJSON[socialId].users.define==undefined)
            {
                accessJSON[socialId].users.define=[];
            }

            console.log('Получаем элемент таблицы таблицы');

            var tds = $(tr).find('div[for=users] table tbody tr td[data-id]');
            $.each(tds,function(k,v){
                if(!accessJSON[socialId].users.define.includes($(v).text()))
                {
                    accessJSON[socialId].users.define.push($(v).text());
                }
            });
        }

        //Получаем доступ к группам
        var acessForGroups = $(tr).find('select[for=groups]');
        console.log('Элемент групп');
        console.log(acessForGroups);

        //Получаем значение
        var valueForGroups = $(acessForGroups).val();
        console.log('Значение групп');
        console.log(valueForGroups);

        if(valueForGroups == 'all' || valueForGroups == 'anybody')
        {
            if(accessJSON[socialId].groups==undefined)
            {
                accessJSON[socialId].groups = {};
            }

            accessJSON[socialId].groups.access = valueForGroups;
        }
        else if(valueForGroups == 'define')
        {
            if(accessJSON[socialId].groups==undefined)
            {
                accessJSON[socialId].groups = {};
            }
            accessJSON[socialId].groups.access = 'define';

            if(accessJSON[socialId].groups.define==undefined)
            {
                accessJSON[socialId].groups.define=[];
            }

            console.log('Получаем элемент таблицы таблицы');

            var tds = $(tr).find('div[for=groups] table tbody tr td[data-id]');
            $.each(tds,function(k,v){
                if(!accessJSON[socialId].groups.define.includes($(v).text()))
                {
                    accessJSON[socialId].groups.define.push($(v).text());
                }
            });
        }
    }

    //Функция клика по галочке соц сети
    function getAccess(tr)
    {
        //Если это не обновление таблицы получаем значения и отправялем на сервак
        if(updateALL)
        {
            return true;
        }
        console.clear();
        var socialId = $(tr).data('social-id');
        console.log('Изменение в социальной сети под id: '+socialId);

        //Получаем элемент галки
        var galka = $(tr).find('[data-change-access]');
        console.log('Элемент галочки:');
        console.log(galka);

        //Если галка стоит
        if($(galka).prop('checked'))
        {
            console.log('Доступ разрещен, считываем более низкий уровень');
            if(accessJSON[socialId]==undefined)
            {
                accessJSON[socialId]={};
            }

            accessJSON[socialId].on=true;
        }
        else
        {
            console.log('Доступ закрыт, удаляем');
            if(accessJSON[socialId]==undefined)
            {
                accessJSON[socialId]={};
            }
            accessJSON[socialId].on=false;
        }

        getValuesUsersOrGroups(tr);
        sendNewAccess();
    }

    $(document).ready(function(){
        console.log('>>>>>>>>>>>>>>>>>> DOCUMENT READY - management.assistant.commands.edit <<<<<<<<<<<<<<<<<<<<<');

        //Открытие окна
        $('#addModal').on('show.bs.modal', function(ev) {
            console.clear();
            var tr = $(ev.relatedTarget).closest('[data-social-id]');
            var socialId = $(tr).data('social-id');
            console.log('Для социальной сети: ' + socialId);
            $('#addModal').attr('data-social-id',socialId);
            var forType = $(ev.relatedTarget).closest('div[for]').attr('for');
            console.log(forType);
            $('#addModal').attr('data-for',forType);
        });

        //Нажата кнопка добавить внутри модального окна
        $('#addModal').on('click','button[data-action-add]',function(e){
            console.clear();
            var value = $('#addModal').find('input').val();
            var tr = $('tr[data-social-id="'+$('#addModal').attr('data-social-id')+'"]');
            var forType = $('#addModal').attr('data-for');
            var DivIDS = $(tr).find('div[for='+forType+']');
            var table = $(DivIDS).find('table tbody');
            var trAppend = $('<tr class="align-middle"><td data-id>'+value+'</td><td><button class="btn btn-danger">X</button></td></tr>');
            $(table).append(trAppend);
            $('#addModal').modal('hide');
            getAccess(tr);
        });

        //Теряем фокус изменяет значение
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

        //Меняем фокус
        $(".type-selecter").change(function(){
            if($(".type-selecter").val()!='none')
            {
                insertForm();
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
        $(".type-selecter").trigger('change');

        //Измениние доступности для социальной сети
        $('input[data-change-access]').change(function(ev){
            var tr = $(ev.target).closest('[data-social-id]');
            getAccess(tr);
        });

        //Изменение доступности для пользоватпелей или групп
        $("select[for]").change(function(){
            var forType = $(this).attr('for');
            var value = $(this).val();
            //Всем скрываем
            if(value=='all')
            {
                $(this).siblings('div[for='+forType+']').hide();
            }
            else if(value=='anybody')
            {
                $(this).siblings('div[for='+forType+']').hide();
            }
            else if(value=='define')
            {
                $(this).siblings('div[for='+forType+']').show();
            }
            var tr = $(this).closest('[data-social-id]');
            getAccess(tr);
        });

        //Обновить данные таблицы
        updateTable();    
    });

    //Функция вставки
    function insertForm()
    {
        $('.form-inserter').empty();
        var selector = $('.form-ins [type='+$(".type-selecter").val()+']');
        var el = $(selector).clone();
        $('.form-inserter').append(el);
    }
</script>

@endsection