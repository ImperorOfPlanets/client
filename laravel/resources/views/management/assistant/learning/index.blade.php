@extends('template.template',[
    'title'=>'Обучение'
])
@section('content')
@push('sidebar') @include('management.sidebar') @endpush

<div class="container-fluid">
    @include('management.assistant.learning.sync')
    @include('management.assistant.learning.search')

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Обучение ИИ</h1>
        <a href="{{ route('m.assistant.learning.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Добавить данные
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="categorySelect" class="form-label">Фильтр по категориям</label>
                    <select class="form-select" id="categorySelect">
                        <option value="">Все категории</option>
                        @foreach($categories as $id => $name)
                            <option value="{{ $id }}" {{ request('category') == $id ? 'selected' : '' }}>
                                {{ $name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="statusSelect" class="form-label">Фильтр по статусу</label>
                    <select class="form-select" id="statusSelect">
                        <option value="">Все статусы</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Ожидание</option>
                        <option value="processing" {{ request('status') == 'processing' ? 'selected' : '' }}>Обработка</option>
                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Завершено</option>
                        <option value="error" {{ request('status') == 'error' ? 'selected' : '' }}>Ошибка</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="button" id="refreshBtn" class="btn btn-outline-secondary">
                            <i class="fas fa-sync-alt"></i> Обновить
                        </button>
                    </div>
                </div>
            </div>
            
            <hr />
            
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="80">ID</th>
                            <th width="120">Статус</th>
                            <th width="120">Категория</th>
                            <th>Содержание</th>
                            <th width="150">Токены/Чанки</th>
                            <th width="150">Дата создания</th>
                            <th width="200">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($embeddings as $embedding)
                        <tr>
                            <td class="text-center">{{ $embedding->id }}</td>
                            <td class="text-center">{!! $embedding->status_badge !!}</td>
                            <td class="text-center">{{ $embedding->category_name }}</td>
                            <td>
                                <div class="text-truncate" style="max-width: 300px;" title="{{ $embedding->content }}">
                                    {{ Str::limit(strip_tags($embedding->content), 100) }}
                                </div>
                            </td>
                            <td class="text-center">
                                @if($embedding->token_count)
                                    {{ $embedding->token_count }} / {{ $embedding->chunk_count }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">{{ $embedding->created_at->format('d.m.Y H:i') }}</td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary view-btn" 
                                        data-id="{{ $embedding->id }}"
                                        data-content="{{ htmlspecialchars($embedding->content) }}"
                                        data-status="{{ $embedding->status }}"
                                        data-vector-id="{{ $embedding->vector_id }}"
                                        data-token-count="{{ $embedding->token_count }}"
                                        data-chunk-count="{{ $embedding->chunk_count }}"
                                        title="Просмотр">
                                    <i class="fas fa-eye"></i> Просмотр
                                </button>
                                    
                                    <a href="{{ route('m.assistant.learning.edit', $embedding->id) }}" 
                                       class="btn btn-outline-warning" 
                                       title="Редактировать">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <form method="POST" action="{{ route('m.assistant.learning.destroy', $embedding->id) }}" 
                                          class="d-inline" onsubmit="return confirm('Удалить эту запись?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="Удалить">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="fas fa-database fa-2x mb-2"></i><br>
                                Нет данных для отображения
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <div class="d-flex justify-content-center mt-3">
                {{ $embeddings->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для просмотра -->
<div class="modal fade" id="contentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Детальная информация о записи #<span id="modalId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Содержимое:</h6>
                        <pre id="modalContent" style="white-space: pre-wrap; font-family: inherit; max-height: 400px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 5px;"></pre>
                    </div>
                    <div class="col-md-6">
                        <h6>Информация о векторах:</h6>
                        <div id="vectorInfo">
                            <p><strong>Статус:</strong> <span id="modalStatus" class="badge"></span></p>
                            <p><strong>ID вектора:</strong> <span id="modalVectorId" class="font-monospace"></span></p>
                            <p><strong>Токены:</strong> <span id="modalTokenCount"></span></p>
                            <p><strong>Чанки:</strong> <span id="modalChunkCount"></span></p>
                        </div>
                        
                        <div class="mt-3" id="actionButtons">
                            <!-- Кнопки будут генерироваться динамически -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>


<script>
$(document).ready(function() {
    const modal = new bootstrap.Modal('#contentModal');

    // Кнопка просмотра
    $('.view-btn').on('click', function() {
        const id = $(this).data('id');
        const content = $(this).data('content');
        const status = $(this).data('status');
        const vectorId = $(this).data('vector-id');
        const tokenCount = $(this).data('token-count');
        const chunkCount = $(this).data('chunk-count');

        $('#modalId').text(id);
        $('#modalContent').text(content);

        // Статус
        const statusBadges = {
            'pending': ['bg-secondary', 'Ожидание'],
            'processing': ['bg-warning', 'Обработка'],
            'completed': ['bg-success', 'Завершено'],
            'error': ['bg-danger', 'Ошибка'],
            'needs_update': ['bg-info', 'Требует обновления']
        };
        const [statusClass, statusText] = statusBadges[status] || ['bg-secondary', 'Неизвестно'];
        $('#modalStatus').attr('class', `badge ${statusClass}`).text(statusText);

        // Векторная информация
        $('#modalVectorId').text(vectorId || 'Не создан')
            .attr('class', vectorId ? 'font-monospace text-success' : 'font-monospace text-muted');
        $('#modalTokenCount').text(tokenCount || 'Не рассчитано');
        $('#modalChunkCount').text(chunkCount || '1');

        // Кнопки действий
        const actionButtons = $('#actionButtons');
        actionButtons.empty();

        // Проверяем наличие векторов через vector_ids, а не vector_id
        const hasVectors = vectorId && vectorId !== 'null' && vectorId !== '';

        if (!hasVectors) {
            actionButtons.append(`
                <button class="btn btn-success btn-sm me-2" id="addToQdrantBtn">
                    <i class="fas fa-database"></i> Добавить в Qdrant
                </button>
            `);
            $('#addToQdrantBtn').off('click').on('click', function() { 
                sendCommand(id, 'add_to_qdrant', 'PUT'); 
            });
        } else {
            actionButtons.append(`
                <button class="btn btn-warning btn-sm me-2" id="recreateBtn">
                    <i class="fas fa-redo"></i> Пересоздать
                </button>
            `);
            $('#recreateBtn').off('click').on('click', function() { 
                sendCommand(id, 'recreate', 'PUT'); 
            });
        }

        actionButtons.append(`
            <button class="btn btn-outline-primary btn-sm mt-2" id="exportBtn">
                <i class="fas fa-download"></i> Экспорт данных
            </button>
        `);
        $('#exportBtn').on('click', function() { exportData(id, content); });

        modal.show();
    });

    // Общая функция для команд
    function sendCommand(id, command, method = 'PUT') {
        if (!confirm('Вы уверены, что хотите выполнить эту команду?')) return;

        const formData = new FormData();
        formData.append('command', command);
        formData.append('_method', method); // Laravel поймёт PUT/DELETE

        $.ajax({
            url: `/management/assistant/learning/${id}`, // URL с ID записи
            type: 'POST', // Laravel принимает POST + _method
            data: formData,
            processData: false,
            contentType: false,
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            success: function(data) {
                if (data.success) {
                    alert(data.message || 'Команда выполнена');
                    location.reload();
                } else {
                    alert('Ошибка: ' + data.message);
                }
            },
            error: function(xhr) {
                alert('Ошибка сети: ' + xhr.statusText);
            }
        });
    }



    // Экспорт
    function exportData(id, content) {
        const blob = new Blob([content], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `embedding-${id}.txt`;
        a.click();
        URL.revokeObjectURL(url);
    }
});
</script>
@endsection