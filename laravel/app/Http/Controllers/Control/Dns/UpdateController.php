<?php

namespace App\Http\Controllers\Control\Dns;

use App\Http\Controllers\Controller;
use App\Jobs\ForControlProjects\Isp\Dns\UpdateRecordJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Models\Core\Objects;

class UpdateController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Логируем получение запроса
        Log::info('DNS update request received', [
            'project_id' => $request->input('project_id'),
            'vpn_ip' => $request->input('vpn_ip'),
            'all_data' => $request->all(),
            'client_ip' => $request->ip(),
        ]);

        // Получаем данные
        $projectId = $request->input('project_id');
        $vpnIp = $request->input('vpn_ip');

        // Проверяем обязательные поля
        if (!$vpnIp || !$projectId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing required fields: project_id and vpn_ip are required'
            ], 422);
        }

        // Получаем проект по ID
        $project = Objects::find($projectId);
        
        if (!$project) {
            return response()->json([
                'status' => 'error',
                'message' => 'Project not found'
            ], 404);
        }

        // Проверяем свойство 77 (домен) проекта
        $domainProperty = $project->propertyById(77);
        
        if (!$domainProperty || !isset($domainProperty->pivot->value)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Domain property (77) not found for project'
            ], 404);
        }

        $fullDomain = $domainProperty->pivot->value;

        // Проверяем, содержит ли домен myidon.site
        if (strpos($fullDomain, 'myidon.site') === false) {
            return response()->json([
                'status' => 'skip',
                'message' => 'Domain does not belong to myidon.site, skipping DNS update',
                'domain' => $fullDomain
            ]);
        }

        // Извлекаем имя субдомена из полного домена
        // Например: из "gitflic.myidon.site" получаем "gitflic"
        $subdomain = str_replace('.myidon.site', '', $fullDomain);

        // Проверяем, что мы получили корректный субдомен (не пустой и не полный домен)
        if (empty($subdomain) || $subdomain === $fullDomain) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid subdomain extracted from domain',
                'domain' => $fullDomain,
                'subdomain' => $subdomain
            ], 422);
        }

        // Логируем информацию о DNS обновлении
        Log::info('DNS update processing', [
            'project_id' => $projectId,
            'full_domain' => $fullDomain,
            'subdomain' => $subdomain,
            'vpn_ip' => $vpnIp
        ]);

        // Обновляем только субдомен проекта (например, gitflic)
        UpdateRecordJob::dispatch('myidon.site', $subdomain, $vpnIp);

        return response()->json([
            'status' => 'ok',
            'message' => 'DNS update queued successfully',
            'data' => [
                'project_id' => $projectId,
                'vpn_ip' => $vpnIp,
                'full_domain' => $fullDomain,
                'subdomain' => $subdomain,
                'record_to_update' => "{$subdomain}.myidon.site",
                'queued_at' => now()->toDateTimeString()
            ]
        ]);
    }
}