{{-- resources/views/management/ai/services/index.blade.php --}}
@extends('template.template', ['title' => 'Управление AI-сервисами'])
@push('sidebar') @include('management.sidebar') @endpush

@section('content')
<div class="container">
    <h2>Управление AI-сервисами</h2>
    
    {{-- Уведомления --}}
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
            @if (session('feature_result'))
                <button type="button" class="btn btn-sm btn-outline-success float-end" 
                        data-bs-toggle="modal" data-bs-target="#resultModal">
                    Показать результат
                </button>
            @endif
        </div>
    @endif
    
    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif
    
    <div class="card">
        <div class="card-body">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Название</th>
                        <th>Класс</th>
                        <th>Возможности</th>
                        <th>Функции</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($services as $serviceItem)
                        <tr>
                            <td>
                                <strong>{{ $serviceItem['name'] }}</strong>
                                @if($serviceItem['db_record'])
                                    <br><small class="text-muted">ID: {{ $serviceItem['db_record']->id }}</small>
                                @endif
                            </td>
                            <td><code>{{ $serviceItem['class'] }}</code></td>
                            <td>
                                @if($serviceItem['capabilities']['regulars'] ?? false)
                                    <span class="badge bg-primary">Текст</span>
                                @endif
                                @if($serviceItem['capabilities']['embeddings'] ?? false)
                                    <span class="badge bg-success">Эмбеддинги</span>
                                @endif
                            </td>
                            <td>
                                @if(count($serviceItem['features']) > 0)
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-info dropdown-toggle" 
                                                type="button" data-bs-toggle="dropdown">
                                            {{ count($serviceItem['features']) }} функций
                                        </button>
                                        <ul class="dropdown-menu">
                                            @foreach($serviceItem['features'] as $featureName => $feature)
                                                <li>
                                                    <form method="POST" 
                                                          action="{{ route('m.ai.services.update', $serviceItem['name']) }}"
                                                          class="d-inline">
                                                        @csrf
                                                        @method('PUT')
                                                        <input type="hidden" name="feature_call" value="1">
                                                        <input type="hidden" name="feature_name" value="{{ $featureName }}">
                                                        
                                                        <button type="submit" class="dropdown-item feature-call-btn"
                                                                data-feature="{{ $featureName }}"
                                                                data-service="{{ $serviceItem['name'] }}">
                                                            <div>
                                                                <strong>{{ $featureName }}</strong>
                                                                @if(!empty($feature['parameters']))
                                                                    <small class="text-muted d-block">
                                                                        Параметры: 
                                                                        {{ implode(', ', array_keys($feature['parameters'])) }}
                                                                    </small>
                                                                @endif
                                                                <small class="text-muted d-block">
                                                                    {{ $feature['description'] }}
                                                                </small>
                                                            </div>
                                                        </button>
                                                    </form>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @else
                                    <span class="text-muted">Нет функций</span>
                                @endif
                            </td>
                            <td>
                                <form action="{{ route('m.ai.services.update', $serviceItem['name']) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="quick_toggle" value="1">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" 
                                               id="switch-{{ $serviceItem['name'] }}"
                                               @if($serviceItem['db_record'] && $serviceItem['db_record']->is_active) checked @endif
                                               onchange="this.form.submit()">
                                        <label class="form-check-label" for="switch-{{ $serviceItem['name'] }}">
                                            {{ $serviceItem['db_record'] && $serviceItem['db_record']->is_active ? 'Активен' : 'Неактивен' }}
                                        </label>
                                    </div>
                                </form>
                            </td>
                            <td>
                                <a href="{{ route('m.ai.services.edit', $serviceItem['name']) }}" 
                                   class="btn btn-sm btn-primary">
                                    <i class="bi bi-gear"></i> Настройки
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">Нет доступных сервисов</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Модальное окно для ввода параметров -->
<div class="modal fade" id="paramsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="paramsForm" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="feature_call" value="1">
                <input type="hidden" name="feature_name" id="modalFeatureName">
                
                <div class="modal-header">
                    <h5 class="modal-title">Параметры функции</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="paramsFields">
                        <!-- Поля параметров будут добавляться динамически -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Выполнить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно для отображения результата -->
@if (session('feature_result'))
<div class="modal fade show" id="resultModal" tabindex="-1" style="display: block;" aria-modal="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Результат функции: {{ session('feature_name') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" 
                        onclick="closeResultModal()"></button>
            </div>
            <div class="modal-body">
                <pre class="p-3 bg-light rounded" style="max-height: 400px; overflow-y: auto;"><code>{{ session('feature_result') }}</code></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeResultModal()">Закрыть</button>
                <button type="button" class="btn btn-success" onclick="copyResult()">Копировать</button>
            </div>
        </div>
    </div>
</div>
<div class="modal-backdrop fade show"></div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Обработка кнопок вызова функций
    document.querySelectorAll('.feature-call-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const featureName = this.dataset.feature;
            const serviceName = this.dataset.service;
            const form = this.closest('form');
            
            // Устанавливаем название функции в скрытое поле
            document.getElementById('modalFeatureName').value = featureName;
            
            // Устанавливаем action формы
            document.getElementById('paramsForm').action = form.action;
            
            // Очищаем предыдущие поля
            document.getElementById('paramsFields').innerHTML = '';
            
            // Для простых функций без параметров сразу отправляем форму
            if (!this.querySelector('.text-muted') || 
                !this.querySelector('.text-muted').textContent.includes('Параметры')) {
                form.submit();
                return;
            }
            
            // Для функций с параметрами показываем модальное окно
            // Здесь можно добавить динамическое создание полей ввода
            // на основе информации о параметрах из data-атрибутов
            
            // Пока просто показываем сообщение
            document.getElementById('paramsFields').innerHTML = 
                '<div class="alert alert-info">Эта функция не требует дополнительных параметров</div>';
            
            // Показываем модальное окно
            new bootstrap.Modal(document.getElementById('paramsModal')).show();
        });
    });
});

function closeResultModal() {
    const modal = document.getElementById('resultModal');
    const backdrop = document.querySelector('.modal-backdrop');
    
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('show');
    }
    
    if (backdrop) {
        backdrop.remove();
    }
}

function copyResult() {
    const resultText = document.querySelector('#resultModal pre code').textContent;
    navigator.clipboard.writeText(resultText).then(() => {
        alert('Результат скопирован в буфер обмена');
    });
}
</script>
@endpush