<?php

namespace App\Jobs\ForControlProjects\Isp\Dns;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Helpers\Control\API\ISPManagerAPI;
use Illuminate\Support\Facades\Log;

class DeleteRecordJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $domain;
    protected string $recordId;
    protected string $recordName;

    public function __construct(string $domain, string $recordId, string $recordName)
    {
        $this->domain = $domain;
        $this->recordId = $recordId;
        $this->recordName = $recordName;
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
    
        // Удаляем DNS запись по ID
        $result = $isp->deleteDnsRecord($this->domain, $this->recordId);
    
        if (isset($result['error'])) {
            Log::error("Failed to delete DNS record {$this->recordName}: " . 
                      ($result['error']['msg']['$'] ?? $result['error']));
        } else {
            Log::info("DNS record deleted successfully: {$this->recordName}.{$this->domain}");
        }
    }
}