@extends('layouts.app')

@section('content')
<div class="container">
    <h1>Шаблоны промтов</h1>
    
    <a href="{{ route('ai.promts.create') }}" class="btn btn-primary mb-3">
        + Новый шаблон
    </a>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Описание</th>
                        <th>Создан</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($templates as $template)
                    <tr>
                        <td>{{ $template->id }}</td>
                        <td>{{ $template->name }}</td>
                        <td>{{ $template->description }}</td>
                        <td>{{ $template->created_at->format('d.m.Y H:i') }}</td>
                        <td>
                            <a href="{{ route('ai.promts.edit', $template) }}" class="btn btn-sm btn-primary">
                                Редактировать
                            </a>
                            <form action="{{ route('ai.promts.destroy', $template) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Удалить шаблон?')">
                                    Удалить
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center">Нет созданных шаблонов</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection