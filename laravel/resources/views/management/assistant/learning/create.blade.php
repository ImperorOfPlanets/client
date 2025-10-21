@extends('template.template',[
    'title'=>'Добавление данных для обучения'
])
@section('content')
@push('sidebar') @include('management.sidebar') @endpush

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Добавление данных для обучения</h1>
        <a href="{{ route('m.assistant.learning.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Назад
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('m.assistant.learning.store') }}" method="POST">
                @csrf
                
                <div class="row mb-3">
                    @if(isset($categories) && count($categories))
                    <div class="col-md-6">
                        <label for="category" class="form-label">Категория</label>
                        <select class="form-select" id="category" name="category">
                            <option value="" selected>Без категории</option>
                            @foreach($categories as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="object_id" class="form-label">Объект</label>
                        <select class="form-select" id="object_id" name="object_id" disabled>
                            <option value="" selected>Сначала выберите категорию</option>
                        </select>
                    </div>
                    @else
                    <input type="hidden" name="category" value="">
                    <input type="hidden" name="object_id" value="">
                    @endif
                </div>
                
                <div class="mb-3">
                    <label for="content" class="form-label">Данные для обучения</label>
                    <textarea class="form-control" id="content" name="content" rows="8" required style="white-space: pre-wrap;" placeholder="Введите текст, который будет преобразован в векторное представление">{{ old('content') }}</textarea>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-brain"></i> Создать векторное представление
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@if(isset($categories) && count($categories))
@push('scripts')
<script>
$(document).ready(function() {
    // Динамическая загрузка объектов при выборе категории
    $('#category').change(function() {
        const categoryId = $(this).val();
        const objectSelect = $('#object_id');
        
        if (!categoryId) {
            objectSelect.prop('disabled', true).val('');
            return;
        }
        
        // Загрузка объектов через AJAX
        $.get(`/management/assistant/learning/objects/${categoryId}`, function(data) {
            objectSelect.empty().prop('disabled', false);
            objectSelect.append('<option value="" selected>Без объекта</option>');
            
            data.forEach(item => {
                objectSelect.append(`<option value="${item.id}">${item.name}</option>`);
            });
        }).fail(function() {
            alert('Ошибка при загрузке объектов');
        });
    });
});
</script>
@endpush
@endif
@endsection