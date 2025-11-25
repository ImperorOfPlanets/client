@extends('template.template',[
	'title'=>'Запросы к ИИ'
])

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/json-formatter-js@2.5.23/dist/json-formatter.umd.min.js"></script>
@endpush
@push('styles')
<style>
    .json-viewer {
        max-height: 400px;
        overflow-y: auto;
        font-size: 14px;
    }
    .badge {
        font-size: 0.875em;
        border-radius: 0.25rem;
        padding: 0.35rem 0.65rem;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }
    .status-badge {
        background-color: #6c757d;
        color: white;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0">AI Запросы</h5>
                    <div>
                        <form action="" id="filter-form" class="me-3" style="display: flex; gap: 0.5rem;">
                            <select name="status" onchange="document.getElementById('filter-form').submit();" 
                                    class="form-select form-select-sm">
                                <option value="">Все статусы</option>
                                @foreach($statuses as $value => $label)
                                    <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </form>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" onclick="toggleCheckboxes(this)" /></th>
                                    <th>Сервис</th>
                                    <th>Запрос</th>
                                    <th>Ответ</th>
                                    <th>Статус</th>
                                    <th>Дата создания</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($requests as $aiRequest)
                                <tr>
                                    <td><input type="checkbox" data-id="{{ $aiRequest->id }}" /></td>
                                    <td>{{ $aiRequest->service->name ?? 'N/A' }}</td>
                                    <td>
                                        <button class="btn btn-sm btn-info show-json-btn" 
                                                data-json='@json($aiRequest->request_data)'>
                                            <i class="fas fa-file-code"></i> Показать
                                        </button>
                                    </td>
                                    <td>
                                        @if($aiRequest->response_data)
                                        <button class="btn btn-sm btn-success show-json-btn" 
                                                data-json='@json($aiRequest->response_data)'>
                                            <i class="fas fa-file-code"></i> Показать
                                        </button>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $statusClass = match($aiRequest->status) {
                                                'pending' => 'warning',
                                                'processing' => 'info',
                                                'completed' => 'success',
                                                'failed' => 'danger',
                                                'retrying' => 'secondary',
                                                default => 'dark',
                                            };
                                        @endphp
                                        
                                        <span class="badge text-white bg-{{ $statusClass }}">
                                            {{ $aiRequest->status }}
                                            @if(($aiRequest->metadata['attempt'] ?? 0) > 0)
                                                <span class="badge bg-white text-dark ms-1">
                                                    {{ $aiRequest->metadata['attempt'] }}
                                                </span>
                                            @endif
                                        </span>
                                    </td>
                                    <td>{{ $aiRequest->created_at->format('d.m.Y H:i') }}</td>
                                    <td>
                                        <a href="{{ route('ai.requests.show', $aiRequest) }}" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if($requests->hasPages())
                        <div class="d-flex justify-content-center mt-4">
                            {{ $requests->links('pagination::bootstrap-5') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для JSON -->
<div id="jsonModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Данные JSON</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body json-viewer"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Обработчик для кнопок показа JSON
    document.querySelectorAll('.show-json-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const jsonData = JSON.parse(this.dataset.json);
            const formatter = new JSONFormatter(jsonData, 2);
            
            document.querySelector('#jsonModal .json-viewer').innerHTML = '';
            document.querySelector('#jsonModal .json-viewer').appendChild(formatter.render());
            
            new bootstrap.Modal(document.getElementById('jsonModal')).show();
        });
    });

    // Массовый выбор
    window.toggleCheckboxes = function(source) {
        const checkboxes = document.querySelectorAll('input[type="checkbox"][data-id]');
        checkboxes.forEach(checkbox => checkbox.checked = source.checked);
    };
});
</script>
@endsection