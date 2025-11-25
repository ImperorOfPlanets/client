<?php

namespace App\Http\Controllers\Management\Ai;

use App\Http\Controllers\Controller;

use App\Models\Ai\AiPromts;
use Illuminate\Http\Request;

class AiPromtsController extends Controller
{
    public function index()
    {
        $templates = AiPromts::latest()->get();
        return view('management.ai.promts.index', compact('templates'));
    }

    public function create()
    {
        return view('management.ai.promts.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:ai_prompt_templates',
            'description' => 'nullable|string',
            'text' => 'required|string',
            'tags' => 'nullable|string', // Строки тегов будут переданы сюда
        ]);

        // Разделяем теги на отдельные элементы
        $tags = explode(',', $request->input('tags'));
        $tags = array_map('trim', $tags); // Чистим от пробелов
    
        // Базовые настройки шаблона
        $settings = [
            'text' => $request->text,
            'variables' => [], // Переменные пока пустые,
            'tags' => $tags, // Сюда попали наши теги
        ];

        // Создаем новый шаблон с указанными настройками
        $promt = AiPromts::create([
            'name' => $request->name,
            'description' => $request->description,
            'settings' => $settings,
        ]);
        return redirect()->route('ai.promts.edit', ['promt' => $promt])->with('success', 'Шаблон успешно создан');
    }

    public function show(AiPromts $template)
    {
        return view('management.ai.promts.show', compact('template'));
    }

    public function edit(AiPromts $promt)
    {
        return view('management.ai.promts.edit', compact('promt'));
    }

    public function update(Request $request, AiPromts $promt)
    {

        $request->validate([
            'name' => 'required|string|max:255|unique:ai_prompt_templates,name,' . $promt->id,
            'description' => 'nullable|string',
            'text' => 'required|string',
            'tags' => 'nullable|string',
            'variables' => 'nullable|array', // Параметр nullable позволит пропускать пустое значение
            'variables.*.name' => 'required|string', // Имя переменной обязательно
            'variables.*.type' => 'required|string', // Тип переменной обязателен
            'variables.*.description' => 'nullable|string', // Опциональное описание
            'variables.*.required' => 'boolean', // Поле должно быть булевым (логическим)
        ]);

        // Парсим теги
        $tags = explode(',', $request->input('tags'));
        $tags = array_map('trim', $tags); // Удаляем лишние пробелы
    
        // Извлекаем и нормализуем массив переменных
        $variables = $request->input('variables', []);
    
        $cleanedText = htmlspecialchars($request->text, ENT_QUOTES, 'UTF-8');
        // Собираем общие настройки шаблона
        $settings = [
            'text' => $cleanedText,
            'variables' => $variables,
            'tags' => $tags,
        ];
    
        // Сохраняем обновление в базу данных
        $promt->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'settings' => $settings,
        ]);
    
        return redirect()->route('ai.promts.index')->with('success', 'Шаблон успешно обновлён');
    }

    public function destroy(AiPromts $template)
    {
        $template->delete();
        return redirect()->route('ai.promts.index')
            ->with('success', 'Шаблон перемещен в корзину');
    }

    public function variables(int $id)
    {
        $template = AiPromts::findOrFail($id);
        return response()->json([
            'variables' => $template->settings['variables']
        ]);
    }
}