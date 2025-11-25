<?php

namespace App\Http\Controllers\Management\Ai;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\Ai\EmbeddingService;

class EmbeddingServicesController extends Controller
{
    public function index(Request $request): View
    {
        $services = EmbeddingService::all();
        
        return view('management.ai.embedding.services.index', [
            'services' => $services
        ]);
    }

    public function edit(string $id): View
    {
        $service = EmbeddingService::findOrFail($id);
        $serviceClass = $service->name;
        
        if (!class_exists($serviceClass)) {
            abort(404, 'Service class not found');
        }

        $requiredSettings = $serviceClass::getRequiredSettings();

        return view('management.ai.embedding.services.edit', [
            'service' => $service,
            'requiredSettings' => $requiredSettings
        ]);
    }

    public function update(Request $request, string $id)
    {
        $service = EmbeddingService::findOrFail($id);
        
        $validated = $request->validate([
            'settings' => 'required|array',
            'is_active' => 'boolean',
            'vector_size' => 'required|integer'
        ]);

        $service->update([
            'settings' => $validated['settings'],
            'is_active' => $validated['is_active'] ?? false,
            'vector_size' => $validated['vector_size']
        ]);

        return redirect()->route('ai.embedding.services.index')
            ->with('success', 'Service updated successfully');
    }
}