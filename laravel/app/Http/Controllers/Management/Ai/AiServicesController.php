<?php

namespace App\Http\Controllers\Management\Ai;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Helpers\Ai\AiServiceLocator;
use App\Models\Ai\AiService;

// app/Http/Controllers/Management/Ai/AiServicesController.php
class AiServicesController extends Controller
{
    public function index(Request $request): View
    {
        $services = AiServiceLocator::getAllServices();
        
        // Добавляем информацию о функциях для каждого сервиса
        foreach ($services as &$serviceItem) {
            if ($serviceItem['db_record']) {
                $serviceInfo = AiServiceLocator::getServiceInfo($serviceItem['db_record']->id);
                $serviceItem['features'] = $serviceInfo['features'] ?? [];
                $serviceItem['capabilities'] = $serviceInfo['capabilities'] ?? [];
            } else {
                $serviceItem['features'] = [];
                $serviceItem['capabilities'] = [];
            }
        }
        
        return view('management.ai.services.index', [
            'services' => $services
        ]);
    }

    public function update(Request $request, string $serviceName)
    {
        $service = AiService::where('name', $serviceName)->first();
        
        if (!$service) {
            $service = new AiService(['name' => $serviceName]);
        }
        
        // Если это быстрый toggle из списка
        if ($request->has('quick_toggle')) {
            $service->is_active = !$service->is_active;
            $service->save();
            
            return redirect()->route('m.ai.services.index')
                ->with('success', 'Статус сервиса успешно изменен!');
        }
        
        // Если это вызов функции сервиса
        if ($request->has('feature_call')) {
            return $this->handleFeatureCall($request, $service);
        }
        
        // Если это обычное обновление из формы редактирования
        $validatedData = $request->validate([
            'settings' => 'sometimes|array',
            'settings.*' => 'nullable|string',
            'is_active' => 'sometimes|boolean'
        ]);

        $isActive = $request->boolean('is_active');
        
        $service->fill([
            'settings' => $validatedData['settings'] ?? [],
            'is_active' => $isActive
        ]);
        
        $service->save();

        return redirect()->route('m.ai.services.index')->with('success', 'Сервис успешно обновлен!');
    }

    /**
     * Обработка вызова функции сервиса через update
     */
    protected function handleFeatureCall(Request $request, AiService $service)
    {
        $featureName = $request->input('feature_name');
        $featureParams = $request->input('feature_params', []);

        if (!$featureName) {
            return redirect()->route('m.ai.services.index')
                ->with('error', 'Не указано название функции');
        }

        // Вызываем функцию через сервис-локатор
        $result = AiServiceLocator::callServiceFeature(
            $service->id, 
            $featureName, 
            $featureParams
        );

        // Сохраняем результат в сессии для отображения
        if ($result['success']) {
            $message = "Функция '{$featureName}' выполнена успешно!";
            
            // Форматируем результат для отображения
            $formattedResult = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
            return redirect()->route('m.ai.services.index')
                ->with('success', $message)
                ->with('feature_result', $formattedResult)
                ->with('feature_name', $featureName);
        } else {
            return redirect()->route('m.ai.services.index')
                ->with('error', "Ошибка выполнения функции '{$featureName}': " . ($result['error'] ?? 'Неизвестная ошибка'));
        }
    }
}