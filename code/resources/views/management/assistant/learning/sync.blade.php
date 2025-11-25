<div class="mb-4">
    <button id="reportEmbeddingsBtn" class="btn btn-warning btn-lg w-100">
        <i class="fas fa-chart-bar"></i> Построить отчёт по эмбеддингам
    </button>
</div>

<!-- Модальное окно -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Отчёт по эмбеддингам</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Здесь можно посмотреть разницу между локальными эмбеддингами и точками в Qdrant.
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <button id="reportExecuteBtn" class="btn btn-success mb-3">
                            <i class="fas fa-play"></i> Запустить отчёт
                        </button>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="selectAllBtn">
                                <i class="fas fa-check-square"></i> Выбрать все
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="deselectAllBtn">
                                <i class="fas fa-square"></i> Снять выделение
                            </button>
                        </div>
                    </div>
                </div>

                <hr />

                <!-- Навигация по типам проблем -->
                <ul class="nav nav-tabs mb-3" id="reportTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="summary-tab" data-bs-toggle="tab" 
                                data-bs-target="#summary" type="button" role="tab">
                            <i class="fas fa-chart-pie"></i> Сводка
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="missing-tab" data-bs-toggle="tab" 
                                data-bs-target="#missing" type="button" role="tab">
                            <i class="fas fa-unlink"></i> Потерянные векторы
                            <span class="badge bg-danger ms-1" id="missingCount">0</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="orphaned-tab" data-bs-toggle="tab" 
                                data-bs-target="#orphaned" type="button" role="tab">
                            <i class="fas fa-ghost"></i> Осиротевшие точки
                            <span class="badge bg-warning ms-1" id="orphanedCount">0</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="no-vectors-tab" data-bs-toggle="tab" 
                                data-bs-target="#no-vectors" type="button" role="tab">
                            <i class="fas fa-ban"></i> Без векторов
                            <span class="badge bg-info ms-1" id="noVectorsCount">0</span>
                        </button>
                    </li>
                </ul>

                <!-- Контент вкладок -->
                <div class="tab-content" id="reportTabContent">
                    <!-- Вкладка сводки -->
                    <div class="tab-pane fade show active" id="summary" role="tabpanel">
                        <div id="summaryResults">
                            <p class="text-muted">Запустите отчёт для просмотра статистики</p>
                        </div>
                    </div>

                    <!-- Вкладка потерянных векторов -->
                    <div class="tab-pane fade" id="missing" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Эмбеддинги с отсутствующими векторами</h6>
                            <div>
                                <button class="btn btn-success btn-sm me-2" id="recreateMissingBtn">
                                    <i class="fas fa-redo"></i> Пересоздать выбранные
                                </button>
                                <button class="btn btn-danger btn-sm" id="deleteMissingBtn">
                                    <i class="fas fa-trash"></i> Удалить выбранные
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th width="30">
                                            <input type="checkbox" id="selectAllMissing">
                                        </th>
                                        <th>ID эмбеддинга</th>
                                        <th>Контент</th>
                                        <th>Отсутствующие векторы</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody id="missingVectorsTable">
                                    <tr>
                                        <td colspan="5" class="text-muted text-center">Нет данных</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Вкладка осиротевших точек -->
                    <div class="tab-pane fade" id="orphaned" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Точки в Qdrant без привязки к эмбеддингам</h6>
                            <div>
                                <button class="btn btn-outline-danger btn-sm me-2" id="deleteAllOrphanedBtn">
                                    <i class="fas fa-broom"></i> Удалить все осиротевшие
                                </button>
                                <button class="btn btn-danger btn-sm" id="deleteOrphanedBtn">
                                    <i class="fas fa-trash"></i> Удалить выбранные
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th width="30">
                                            <input type="checkbox" id="selectAllOrphaned">
                                        </th>
                                        <th>ID точки</th>
                                        <th>Контент</th>
                                        <th>Родитель</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody id="orphanedPointsTable">
                                    <tr>
                                        <td colspan="5" class="text-muted text-center">Нет данных</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Вкладка без векторов -->
                    <div class="tab-pane fade" id="no-vectors" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Эмбеддинги без созданных векторов</h6>
                            <div>
                                <button class="btn btn-success btn-sm me-2" id="createVectorsBtn">
                                    <i class="fas fa-plus"></i> Создать векторы
                                </button>
                                <button class="btn btn-danger btn-sm" id="deleteNoVectorsBtn">
                                    <i class="fas fa-trash"></i> Удалить выбранные
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th width="30">
                                            <input type="checkbox" id="selectAllNoVectors">
                                        </th>
                                        <th>ID эмбеддинга</th>
                                        <th>Контент</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody id="noVectorsTable">
                                    <tr>
                                        <td colspan="4" class="text-muted text-center">Нет данных</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="reportProgress" class="progress mt-3" style="display: none; height: 20px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         role="progressbar" style="width: 0%"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    const modal = new bootstrap.Modal('#reportModal');
    let reportFile = null;
    let statusInterval = null;
    let currentReportData = null;

    // Инициализация модального окна
    $('#reportEmbeddingsBtn').on('click', function() {
        modal.show();
        resetReport();
    });

    // Сброс отчёта
    function resetReport() {
        $('#reportResults').html('<p class="text-muted">Запустите отчёт для просмотра статистики</p>');
        $('#missingVectorsTable, #orphanedPointsTable, #noVectorsTable').html('<tr><td colspan="5" class="text-muted text-center">Нет данных</td></tr>');
        $('#reportProgress').hide();
        $('#missingCount, #orphanedCount, #noVectorsCount').text('0');
        clearInterval(statusInterval);
        reportFile = null;
        currentReportData = null;
    }

    // Запуск отчёта
    $('#reportExecuteBtn').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Запуск...');

        $('#reportProgress').show().find('.progress-bar').css('width', '10%');
        $('.tab-pane .table-responsive').hide();

        $.ajax({
            url: '{{ route("m.assistant.learning.store") }}',
            type: 'POST',
            data: JSON.stringify({ command: 'report_embeddings' }),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            success: function(data) {
                if (data.success) {
                    reportFile = data.report_file;
                    $('#reportProgress .progress-bar').css('width', '30%');
                    statusInterval = setInterval(() => checkReportStatus(btn), 2000);
                } else {
                    showError(data.message);
                    btn.prop('disabled', false).html('<i class="fas fa-play"></i> Запустить отчёт');
                }
            },
            error: function(xhr) {
                showError('Ошибка сети: ' + xhr.statusText);
                btn.prop('disabled', false).html('<i class="fas fa-play"></i> Запустить отчёт');
            }
        });
    });

    // Проверка статуса отчёта
    function checkReportStatus(btn) {
        if (!reportFile) return;
        
        $.ajax({
            url: '{{ route("m.assistant.learning.store") }}',
            type: 'POST',
            data: JSON.stringify({ command: 'report_status', report_file: reportFile }),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            success: function(data) {
                if (data.status === 'processing') {
                    $('#reportProgress .progress-bar').css('width', '60%');
                } else if (data.status === 'completed') {
                    $('#reportProgress .progress-bar').css('width', '100%');
                    currentReportData = data.results;
                    renderReport(data.results);
                    clearInterval(statusInterval);
                    btn.prop('disabled', false).html('<i class="fas fa-play"></i> Запустить отчёт');
                    setTimeout(() => $('#reportProgress').hide(), 1000);
                }
            },
            error: function(xhr) {
                showError('Ошибка проверки статуса: ' + xhr.statusText);
                clearInterval(statusInterval);
                btn.prop('disabled', false).html('<i class="fas fa-play"></i> Запустить отчёт');
            }
        });
    }

    // Рендер отчёта
    function renderReport(results) {
        renderSummary(results);
        renderMissingVectors(results.details.missing_vectors);
        renderOrphanedPoints(results.details.orphaned_points);
        renderNoVectors(results.details.no_vectors);
        
        // Показываем таблицы
        $('.tab-pane .table-responsive').show();
    }

    // Рендер сводки
    function renderSummary(results) {
        const s = results.summary;
        let html = `
            <div class="row">
                <div class="col-md-3">
                    <div class="card text-white bg-primary mb-3">
                        <div class="card-body text-center">
                            <h4>${s.total_raw_embeddings}</h4>
                            <p class="card-text">Сырых эмбеддингов</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success mb-3">
                        <div class="card-body text-center">
                            <h4>${s.total_vector_points}</h4>
                            <p class="card-text">Векторных точек</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-white bg-info mb-3">
                        <div class="card-body text-center">
                            <h4>${s.no_vectors}</h4>
                            <p class="card-text">Без векторов</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-white bg-danger mb-3">
                        <div class="card-body text-center">
                            <h4>${s.missing_vectors}</h4>
                            <p class="card-text">Потерянных</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-white bg-warning mb-3">
                        <div class="card-body text-center">
                            <h4>${s.orphaned_points}</h4>
                            <p class="card-text">Осиротевших</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        $('#summaryResults').html(html);
    }

    // Рендер потерянных векторов
    function renderMissingVectors(missingVectors) {
        $('#missingCount').text(missingVectors.length);
        
        if (missingVectors.length === 0) {
            $('#missingVectorsTable').html('<tr><td colspan="5" class="text-muted text-center">Нет потерянных векторов</td></tr>');
            return;
        }

        let html = '';
        missingVectors.forEach(item => {
            html += `
                <tr>
                    <td><input type="checkbox" class="missing-checkbox" value="${item.embedding_id}"></td>
                    <td><strong>${item.embedding_id}</strong></td>
                    <td>
                        <div class="text-truncate" style="max-width: 300px;" 
                             title="${escapeHtml(item.content_preview)}">
                            ${escapeHtml(item.content_preview)}
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-danger">${item.missing_vector_ids.length} векторов</span>
                        <small class="d-block text-muted">${item.missing_vector_ids.join(', ')}</small>
                    </td>
                    <td>
                        <button class="btn btn-success btn-sm me-1 recreate-single" 
                                data-id="${item.embedding_id}" title="Пересоздать векторы">
                            <i class="fas fa-redo"></i>
                        </button>
                        <button class="btn btn-danger btn-sm delete-single" 
                                data-id="${item.embedding_id}" data-type="missing" title="Удалить эмбеддинг">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        $('#missingVectorsTable').html(html);
    }

    // Рендер осиротевших точек
    function renderOrphanedPoints(orphanedPoints) {
        $('#orphanedCount').text(orphanedPoints.length);
        
        if (orphanedPoints.length === 0) {
            $('#orphanedPointsTable').html('<tr><td colspan="5" class="text-muted text-center">Нет осиротевших точек</td></tr>');
            return;
        }

        let html = '';
        orphanedPoints.forEach(point => {
            // ИСПРАВЛЕНИЕ: используем правильную структуру данных
            const text = point.payload?.text || point.text || 'Нет текста';
            const parentId = point.payload?.parent_id || point.parent_id || 'Нет родителя';
            const chunkId = point.payload?.chunk_id ?? null;
            const totalChunks = point.payload?.total_chunks ?? null;
            
            const textPreview = escapeHtml(text.substring(0, 100));
            
            html += `
                <tr>
                    <td><input type="checkbox" class="orphaned-checkbox" value="${point.point_id}"></td>
                    <td><strong>${point.point_id}</strong></td>
                    <td>
                        <div class="text-truncate" style="max-width: 300px;" title="${textPreview}">
                            ${textPreview}
                        </div>
                    </td>
                    <td>
                        ${parentId !== 'Нет родителя' ? `Родитель: ${parentId}` : parentId}
                        ${chunkId !== null ? `<br><small>Чанк: ${chunkId + 1}/${totalChunks}</small>` : ''}
                    </td>
                    <td>
                        <button class="btn btn-danger btn-sm delete-orphaned-single" 
                                data-id="${point.point_id}" title="Удалить точку">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        $('#orphanedPointsTable').html(html);
    }

    // Рендер эмбеддингов без векторов
    function renderNoVectors(noVectors) {
        $('#noVectorsCount').text(noVectors.length);
        
        if (noVectors.length === 0) {
            $('#noVectorsTable').html('<tr><td colspan="4" class="text-muted text-center">Нет эмбеддингов без векторов</td></tr>');
            return;
        }

        let html = '';
        noVectors.forEach(item => {
            html += `
                <tr>
                    <td><input type="checkbox" class="no-vectors-checkbox" value="${item.embedding_id}"></td>
                    <td><strong>${item.embedding_id}</strong></td>
                    <td>
                        <div class="text-truncate" style="max-width: 300px;" 
                             title="${escapeHtml(item.content_preview)}">
                            ${escapeHtml(item.content_preview)}
                        </div>
                    </td>
                    <td>
                        <button class="btn btn-success btn-sm me-1 create-single" 
                                data-id="${item.embedding_id}" title="Создать векторы">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="btn btn-danger btn-sm delete-single" 
                                data-id="${item.embedding_id}" data-type="no_vectors" title="Удалить эмбеддинг">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        $('#noVectorsTable').html(html);
    }

    // Управление выделением
    $('#selectAllBtn').on('click', function() {
        $('input[type="checkbox"]').prop('checked', true);
    });

    $('#deselectAllBtn').on('click', function() {
        $('input[type="checkbox"]').prop('checked', false);
    });

    // Выделение всех в таблицах
    $('#selectAllMissing').on('change', function() {
        $('.missing-checkbox').prop('checked', this.checked);
    });

    $('#selectAllOrphaned').on('change', function() {
        $('.orphaned-checkbox').prop('checked', this.checked);
    });

    $('#selectAllNoVectors').on('change', function() {
        $('.no-vectors-checkbox').prop('checked', this.checked);
    });

    // Действия с потерянными векторами
    $('#recreateMissingBtn').on('click', function() {
        const selectedIds = getSelectedIds('.missing-checkbox:checked');
        if (selectedIds.length === 0) {
            alert('Выберите хотя бы один эмбеддинг для пересоздания');
            return;
        }
        recreateVectors(selectedIds);
    });

    $('#deleteMissingBtn').on('click', function() {
        const selectedIds = getSelectedIds('.missing-checkbox:checked');
        if (selectedIds.length === 0) {
            alert('Выберите хотя бы один эмбеддинг для удаления');
            return;
        }
        deleteEmbeddings(selectedIds, 'missing');
    });

    // Действия с осиротевшими точками
    $('#deleteOrphanedBtn').on('click', function() {
        const selectedIds = getSelectedIds('.orphaned-checkbox:checked');
        if (selectedIds.length === 0) {
            alert('Выберите хотя бы одну точку для удаления');
            return;
        }
        deleteOrphanedPoints(selectedIds);
    });

    // Удаление всех осиротевших точек
    $('#deleteAllOrphanedBtn').on('click', function() {
        if (!currentReportData) {
            alert('Сначала запустите отчёт, чтобы увидеть все осиротевшие точки');
            return;
        }

        const orphanedPoints = currentReportData.details.orphaned_points || [];
        if (orphanedPoints.length === 0) {
            alert('Нет осиротевших точек для удаления');
            return;
        }

        const allIds = orphanedPoints.map(point => point.point_id);
        if (!confirm(`Удалить ВСЕ (${allIds.length}) осиротевшие точки? Это действие нельзя отменить.`)) {
            return;
        }
        
        const btn = $(this);
        const originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Удаление...');

        $.ajax({
            url: '{{ route("m.assistant.learning.store") }}',
            type: 'POST',
            data: JSON.stringify({ 
                command: 'delete_all_orphaned_points'
            }),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            success: function(data) {
                if (data.success) {
                    alert(data.message);
                    $('#reportExecuteBtn').click(); // Обновляем отчет
                } else {
                    alert('Ошибка: ' + data.message);
                }
                btn.prop('disabled', false).html(originalText);
            },
            error: function(xhr) {
                alert('Ошибка сети: ' + xhr.statusText);
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // Действия с эмбеддингами без векторов
    $('#createVectorsBtn').on('click', function() {
        const selectedIds = getSelectedIds('.no-vectors-checkbox:checked');
        if (selectedIds.length === 0) {
            alert('Выберите хотя бы один эмбеддинг для создания векторов');
            return;
        }
        createVectors(selectedIds);
    });

    $('#deleteNoVectorsBtn').on('click', function() {
        const selectedIds = getSelectedIds('.no-vectors-checkbox:checked');
        if (selectedIds.length === 0) {
            alert('Выберите хотя бы один эмбеддинг для удаления');
            return;
        }
        deleteEmbeddings(selectedIds, 'no_vectors');
    });

    // Одиночные действия
    $(document).on('click', '.recreate-single', function() {
        const id = $(this).data('id');
        recreateVectors([id]);
    });

    $(document).on('click', '.create-single', function() {
        const id = $(this).data('id');
        createVectors([id]);
    });

    $(document).on('click', '.delete-single', function() {
        const id = $(this).data('id');
        const type = $(this).data('type');
        if (confirm(`Удалить эмбеддинг ${id}?`)) {
            deleteEmbeddings([id], type);
        }
    });

    $(document).on('click', '.delete-orphaned-single', function() {
        const id = $(this).data('id');
        const row = $(this).closest('tr');
        const content = row.find('td:nth-child(3)').text().trim();
        
        if (confirm(`Удалить точку ${id} из Qdrant?\n\nКонтент: ${content.substring(0, 100)}...`)) {
            const btn = $(this);
            const originalHtml = btn.html();
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            
            deleteOrphanedPoints([id]);
            
            // Через 3 секунды разблокируем кнопку на случай ошибки
            setTimeout(() => {
                btn.prop('disabled', false).html(originalHtml);
            }, 3000);
        }
    });

    // Вспомогательные функции
    function getSelectedIds(selector) {
        const ids = [];
        $(selector).each(function() {
            ids.push($(this).val());
        });
        return ids;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showError(message) {
        $('#summaryResults').html(`<div class="alert alert-danger">${message}</div>`);
    }

    // AJAX функции для действий
    function recreateVectors(ids) {
        $.ajax({
            url: '{{ route("m.assistant.learning.store") }}',
            type: 'POST',
            data: JSON.stringify({ 
                command: 'recreate_vectors', 
                embedding_ids: ids 
            }),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            success: function(data) {
                if (data.success) {
                    alert('Векторы поставлены в очередь на пересоздание');
                    $('#reportExecuteBtn').click(); // Обновляем отчёт
                } else {
                    alert('Ошибка: ' + data.message);
                }
            },
            error: function(xhr) {
                alert('Ошибка сети: ' + xhr.statusText);
            }
        });
    }

    function createVectors(ids) {
        $.ajax({
            url: '{{ route("m.assistant.learning.store") }}',
            type: 'POST',
            data: JSON.stringify({ 
                command: 'create_vectors', 
                embedding_ids: ids 
            }),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            success: function(data) {
                if (data.success) {
                    alert('Векторы поставлены в очередь на создание');
                    $('#reportExecuteBtn').click(); // Обновляем отчёт
                } else {
                    alert('Ошибка: ' + data.message);
                }
            },
            error: function(xhr) {
                alert('Ошибка сети: ' + xhr.statusText);
            }
        });
    }

    function deleteEmbeddings(ids, type) {
        $.ajax({
            url: '{{ route("m.assistant.learning.store") }}',
            type: 'POST',
            data: JSON.stringify({ 
                command: 'delete_embeddings', 
                embedding_ids: ids 
            }),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            success: function(data) {
                if (data.success) {
                    alert('Эмбеддинги удалены');
                    $('#reportExecuteBtn').click(); // Обновляем отчёт
                } else {
                    alert('Ошибка: ' + data.message);
                }
            },
            error: function(xhr) {
                alert('Ошибка сети: ' + xhr.statusText);
            }
        });
    }

    function deleteOrphanedPoints(ids) {
        if (!confirm(`Вы уверены, что хотите удалить ${ids.length} осиротевших точек из Qdrant?\n\nЭто действие нельзя отменить.`)) {
            return;
        }
        
        // Визуальная обратная связь
        ids.forEach(id => {
            $(`.delete-orphaned-single[data-id="${id}"]`)
                .closest('tr')
                .addClass('table-warning');
        });

        const deleteBtn = $('#deleteOrphanedBtn');
        const originalText = deleteBtn.html();
        deleteBtn.prop('disabled', true).html(`<i class="fas fa-spinner fa-spin"></i> Удаление...`);

        $.ajax({
            url: '{{ route("m.assistant.learning.store") }}',
            type: 'POST',
            data: JSON.stringify({ 
                command: 'delete_orphaned_points', 
                point_ids: ids 
            }),
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            success: function(data) {
                if (data.success) {
                    // Удаляем строки из таблицы
                    ids.forEach(id => {
                        $(`.delete-orphaned-single[data-id="${id}"]`)
                            .closest('tr')
                            .fadeOut(300, function() {
                                $(this).remove();
                            });
                    });
                    
                    // Обновляем счетчик
                    const remaining = $('.orphaned-checkbox').length;
                    $('#orphanedCount').text(remaining);
                    
                    alert(data.message);
                    
                    // Если все удалено, показываем сообщение
                    if (remaining === 0) {
                        $('#orphanedPointsTable').html('<tr><td colspan="5" class="text-muted text-center">Нет осиротевших точек</td></tr>');
                    }
                } else {
                    alert('Ошибка: ' + data.message);
                    // Возвращаем визуальное состояние
                    ids.forEach(id => {
                        $(`.delete-orphaned-single[data-id="${id}"]`)
                            .closest('tr')
                            .removeClass('table-warning');
                    });
                }
                deleteBtn.prop('disabled', false).html(originalText);
            },
            error: function(xhr) {
                alert('Ошибка сети: ' + xhr.statusText);
                // Возвращаем визуальное состояние
                ids.forEach(id => {
                    $(`.delete-orphaned-single[data-id="${id}"]`)
                        .closest('tr')
                        .removeClass('table-warning');
                });
                deleteBtn.prop('disabled', false).html(originalText);
            }
        });
    }

    // Закрытие модального окна
    $('#reportModal').on('hidden.bs.modal', function() {
        clearInterval(statusInterval);
        reportFile = null;
        currentReportData = null;
    });
});
</script>


<style>
.orphaned-point-row.deleting {
    opacity: 0.6;
    background-color: #fff3cd !important;
}

.orphaned-point-row.deleted {
    display: none;
}

.progress-sm {
    height: 5px;
}

.batch-actions {
    position: sticky;
    bottom: 0;
    background: white;
    padding: 10px;
    border-top: 1px solid #dee2e6;
    margin-top: 10px;
}
</style>