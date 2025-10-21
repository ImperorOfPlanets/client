@extends('template.template',[
	'title'=>'Редактирование настроек AI-сервиса'
])
@push('sidebar') @include('management.sidebar') @endpush
@section('content')
<div class="container">
    <h2>Редактирование настроек AI-сервиса "{{ $service->name }}"</h2>
    
    <form method="POST" action="{{ route('m.ai.services.update', $service->name) }}">
        @csrf
        @method('PUT')
        
        <!-- Активность -->
        <div class="mb-3 form-check">
            <input type="checkbox" name="is_active" id="is-active" value="1" class="form-check-input" 
                   @checked(old('is_active', $service->is_active)) />
            <label for="is-active" class="form-check-label">Активирован</label>
        </div>
        
        <!-- Динамические поля настроек -->
        @foreach ($requiredSettings as $setting)
            <div class="mb-3">
                <label for="{{ $setting['key'] }}" class="form-label"><strong>{{ $setting['label'] }}</strong></label>
                <p><small>{{ $setting['description'] }}</small></p>
                <input type="text" name="settings[{{ $setting['key'] }}]" id="{{ $setting['key'] }}" class="form-control" 
                       placeholder="Значение {{ $setting['label'] }}"
                       value="{{ old("settings.$setting[key]", $service->settings[$setting['key']] ?? '') }}"/>
                
                @if ($setting['required'])
                    <span class="text-danger">* Поле обязательно для заполнения.</span>
                @endif
            </div>
        @endforeach
        
        <button type="submit" class="btn btn-primary">Обновить настройки</button>
    </form>
</div>
@endsection