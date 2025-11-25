<?php

namespace App\Jobs\ForControlProjects\Isp\Dns;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Helpers\Control\API\ISPManagerAPI;
use Illuminate\Support\Facades\Log;

class GetRecordJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $domain;
    protected ?string $recordName;

    public function __construct(string $domain, ?string $recordName = null)
    {
        $this->domain = $domain;
        $this->recordName = $recordName;
    }

    public function handle(): array
    {
        $isp = new ISPManagerAPI(
            env('ISPMANAGER_URL'),
            env('ISPMANAGER_LOGIN'),
            env('ISPMANAGER_PASSWORD')
        );
    
        $auth = $isp->auth();
        if (!empty($auth['error'])) {
            Log::error("ISPManager auth error: {$auth['error']}");
            return ['error' => $auth['error']];
        }
    
        // Получаем DNS записи домена
        $dnsRecords = $isp->getDnsRecords($this->domain);
        
        if (isset($dnsRecords['error'])) {
            Log::error("Failed to get DNS records: {$dnsRecords['error']}");
            return $dnsRecords;
        }
        
        if (!$this->recordName) {
            return $dnsRecords;
        }
        
        // Фильтруем по имени записи
        $filteredRecords = [];
        foreach ($dnsRecords as $record) {
            $currentRecordName = $record['name'] ?? '';
            $cleanRecordName = str_replace('.' . $this->domain, '', $currentRecordName);
            
            if ($cleanRecordName === $this->recordName || $currentRecordName === $this->recordName) {
                $filteredRecords[] = $record;
            }
        }
        
        return $filteredRecords;
    }
}