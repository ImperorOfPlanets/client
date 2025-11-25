<?php

namespace App\Http\Controllers\Management\Ai;

use App\Http\Controllers\Controller;

use App\Models\Ai\AiRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiRequestsController extends Controller
{
    public function index(Request $request): View
    {
        $requests = AiRequest::with('service')
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20);

        $statuses = AiRequest::getStatuses(); // Добавьте метод в модель

        return view('management.ai.requests', compact('requests', 'statuses'));
    }

    public function show(AiRequest $request): View
    {
        return view('management.ai.requests.show', compact('request'));
    }
}