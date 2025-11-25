@if(isset($result) && $result)
<div class="execution-results">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6>Результаты выполнения</h6>
        <span class="badge badge-{{ $result['status'] === 'success' ? 'success' : 'danger' }}">
            {{ $result['status'] }}
        </span>
    </div>

    @if(isset($result['error']))
    <div class="alert alert-danger">
        <strong>Ошибка:</strong> {{ $result['error']['message'] }}
        @if(isset($result['error']['code']))
        <br><small>Код: {{ $result['error']['code'] }}</small>
        @endif
    </div>
    @endif

    @if(isset($result['results']) && is_array($result['results']))
    <div class="results-list">
        @foreach($result['results'] as $index => $stepResult)
        <div class="card mb-3">
            <div class="card-header py-2">
                <h6 class="mb-0">
                    Шаг {{ $index + 1 }}: 
                    <span class="text-capitalize">{{ $stepResult['action'] }}</span>
                    <span class="badge badge-{{ $stepResult['status'] === 'success' ? 'success' : 'danger' }} float-right">
                        {{ $stepResult['status'] }}
                    </span>
                </h6>
            </div>
            <div class="card-body p-3">
                <!-- Детали выполнения -->
                @if(isset($stepResult['url']))
                <p><strong>URL:</strong> <a href="{{ $stepResult['url'] }}" target="_blank">{{ $stepResult['url'] }}</a></p>
                @endif
                
                @if(isset($stepResult['query']))
                <p><strong>Запрос:</strong> {{ $stepResult['query'] }}</p>
                @endif
                
                <!-- Скриншот -->
                @if(isset($stepResult['screenshot']))
                <div class="mb-3">
                    <strong>Скриншот:</strong>
                    <div class="mt-2">
                        <img src="{{ $stepResult['screenshot'] }}" 
                             class="img-thumbnail" 
                             style="max-height: 200px; cursor: pointer;"
                             onclick="showScreenshot('{{ $stepResult['screenshot'] }}')"
                             alt="Скриншот шага {{ $index + 1 }}">
                    </div>
                </div>
                @endif
                
                <!-- Извлеченный текст -->
                @if(isset($stepResult['items']) && is_array($stepResult['items']))
                <div class="mb-3">
                    <strong>Извлеченные элементы ({{ count($stepResult['items']) }}):</strong>
                    <div class="mt-2">
                        @foreach($stepResult['items'] as $item)
                        <div class="border-bottom pb-1 mb-1">{{ $item }}</div>
                        @endforeach
                    </div>
                </div>
                @endif
                
                <!-- Текст страницы -->
                @if(isset($stepResult['text']))
                <div class="mb-3">
                    <strong>Текст страницы:</strong>
                    <div class="mt-2">
                        <textarea class="form-control" rows="4" readonly>{{ Str::limit($stepResult['text'], 1000) }}</textarea>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <!-- Сводная информация -->
    <div class="card bg-light">
        <div class="card-body py-2">
            <small class="text-muted">
                <strong>Request ID:</strong> {{ $result['request_id'] ?? 'N/A' }} | 
                <strong>Время:</strong> {{ now()->format('H:i:s') }}
            </small>
        </div>
    </div>
</div>
@else
<div class="text-center text-muted py-4">
    <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
    <p>Нет данных для отображения</p>
</div>
@endif