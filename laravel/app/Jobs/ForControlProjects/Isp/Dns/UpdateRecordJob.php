<?php

namespace App\Jobs\ForControlProjects\Isp\Dns;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Helpers\Control\API\ISPManagerAPI;
use Illuminate\Support\Facades\Log;
use Dotenv\Dotenv;

class UpdateRecordJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $domain;
    protected $recordName;
    protected $ip;

    public function __construct($domain, $recordName, $ip)
    {
        $this->domain = $domain;
        $this->recordName = $recordName;
        $this->ip = $ip;
    }

    public function handle()
    {
        Log::info('UpdateRecordJob started', [
            'domain' => $this->domain,
            'record_name' => $this->recordName,
            'ip' => $this->ip
        ]);

        // Загружаем .env файл как в команде
        $this->loadEnvFile('/var/www/html/.env');

        $isp = new ISPManagerAPI(
            env('ISPMANAGER_URL'),
            env('ISPMANAGER_LOGIN'),
            env('ISPMANAGER_PASSWORD')
        );

        // Авторизация
        $auth = $isp->auth();
        if (!empty($auth['error'])) {
            Log::error("ISPManager auth failed: {$auth['error']}");
            $this->fail(new \Exception("ISPManager auth failed: {$auth['error']}"));
            return;
        }

        // Получаем все DNS записи
        $dnsRecords = $isp->getDnsRecords($this->domain);
        if (isset($dnsRecords['error'])) {
            Log::error("Failed to get DNS records: {$dnsRecords['error']}");
            $this->fail(new \Exception("Failed to get DNS records: {$dnsRecords['error']}"));
            return;
        }

        // Ищем существующую запись
        $existingRecord = null;
        foreach ($dnsRecords as $record) {
            $recordType = $record['type'] ?? '';
            $recordName = rtrim($record['name'] ?? '', '.');
            
            if ($recordType === 'A' && ($recordName === $this->recordName || $recordName === "{$this->recordName}.{$this->domain}")) {
                $existingRecord = $record;
                break;
            }
        }

        if ($existingRecord) {
            $existingIp = $existingRecord['value'] ?? $existingRecord['address'] ?? null;
            $recordId = $existingRecord['id'] ?? $existingRecord['rkey'] ?? null;

            if ($existingIp === $this->ip) {
                Log::info("✅ DNS record {$this->recordName}.{$this->domain} is already up to date");
                return;
            }

            // Обновляем существующую запись
            Log::info("Updating DNS record {$this->recordName}.{$this->domain}: {$existingIp} → {$this->ip}");
            $result = $isp->updateDnsRecord(
                $this->domain,
                $recordId,
                $this->recordName,
                'A',
                $this->ip,
                300
            );
        } else {
            // Создаем новую запись
            Log::info("Creating new DNS record: {$this->recordName}.{$this->domain} → {$this->ip}");
            $result = $isp->createDnsRecord(
                $this->domain,
                $this->recordName,
                'A',
                $this->ip,
                300
            );
        }

        // Проверяем результат
        if (isset($result['error'])) {
            Log::error("DNS update failed: {$result['error']}");
            $this->fail(new \Exception("DNS update failed: {$result['error']}"));
        } else {
            Log::info("✅ DNS record {$this->recordName}.{$this->domain} successfully " . ($existingRecord ? 'updated' : 'created'));
        }
    }

    protected function loadEnvFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            Log::error("Env file not found: {$filePath}");
            return;
        }

        $dotenv = Dotenv::createMutable(dirname($filePath), basename($filePath));
        
        try {
            $dotenv->load();
        } catch (\Exception $e) {
            Log::error("Error loading .env file: {$e->getMessage()}");
        }
    }

    public function failed(\Exception $exception)
    {
        Log::error('UpdateRecordJob failed: ' . $exception->getMessage());
    }
}