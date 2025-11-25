@extends('template.template',[
    'title'=>'Редактирование данных обучения'
])
@section('content')
@push('sidebar') @include('management.sidebar') @endpush

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Редактирование данных обучения</h1>
        <a href="{{ route('m.assistant.learning.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Назад
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('m.assistant.learning.update', $embedding->id) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="row mb-3">
                    @if(count($categories))
                    <div class="col-md-6">
                        <label for="category" class="form-label">Категория</label>
                        <select class="form-select" id="category" name="category">
                            <option value="" {{ is_null($embedding->category_id) ? 'selected' : '' }}>Без категории</option>
                            @foreach($categories as $id => $name)
                                <option value="{{ $id }}" {{ $embedding->category_id == $id ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @else
                    <input type="hidden" name="category" value="">
                    @endif
                </div>
                
                <div class="mb-3">
                    <label for="content" class="form-label">Данные для обучения</label>
                    <textarea class="form-control" id="content" name="content" rows="8" required
                              style="white-space: pre-wrap; font-family: monospace;">{{ old('content', $embedding->content) }}</textarea>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Сохранить изменения
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection