<?php

namespace App\Jobs\ForControlProjects\Isp\Dns;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Helpers\Control\API\ISPManagerAPI;
use Illuminate\Support\Facades\Log;

class CreateRecordJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $domain;
    protected array $recordData;

    public function __construct(string $domain, array $recordData)
    {
        $this->domain = $domain;
        $this->recordData = $recordData;
    }

    public function handle(): void
    {
        $isp = new ISPManagerAPI(
            env('ISPMANAGER_URL'),
            env('ISPMANAGER_LOGIN'),
            env('ISPMANAGER_PASSWORD')
        );
    
        $auth = $isp->auth();
        if (!empty($auth['error'])) {
            Log::error("ISPManager auth error: {$auth['error']}");
            return;
        }
    
        // Создаем DNS запись
        $result = $isp->createOrUpdateDnsRecord(
            $this->domain,
            $this->recordData['name'],
            $this->recordData['type'],
            $this->recordData['value'],
            $this->recordData['ttl'] ?? 300
        );
    
        if (isset($result['error'])) {
            Log::error("Failed to create DNS record for {$this->recordData['name']}: " . 
                      ($result['error']['msg']['$'] ?? $result['error']));
        } else {
            Log::info("DNS record created successfully: {$this->recordData['name']}.{$this->domain}");
        }
    }
}