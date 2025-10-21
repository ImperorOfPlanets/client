<?php

namespace App\Helpers\Ai;

use ReflectionClass;
use App\Models\Ai\AiService;
use App\Helpers\Ai\AiServices;
use Illuminate\Support\Facades\Log;

// app/Helpers/Ai/AiServiceLocator.php
class AiServiceLocator
{
    /**
     * Получение сервисов для запроса
     * Если указан service_id - используем только его (если активен)
     * Если не указан - используем все активные сервисы
     */
    public static function getServicesForRequest(?int $preferredServiceId = null): array
    {
        return self::getAllActiveServices($preferredServiceId);
    }

    /**
     * Получение всех активных сервисов
     * - Если указан конкретный ID: используем только его (если активен)
     * - Если не указан: используем все активные сервисы
     */
    public static function getAllActiveServices($preferredServiceId = null): array
    {
        // Если указан конкретный сервис
        if ($preferredServiceId) {
            $service = self::getServiceById($preferredServiceId);
            if ($service) {
                Log::debug('Используем указанный сервис', [
                    'service_id' => $preferredServiceId,
                    'service_name' => $service::getName()
                ]);
                return [$service];
            }
            
            // Если указанный сервис недоступен - логируем и используем все активные
            Log::warning('Указанный сервис недоступен, используем все активные', [
                'requested_service_id' => $preferredServiceId
            ]);
        }

        // Используем все активные сервисы
        return self::getAllEnabledServices();
    }

    /**
     * Получение всех включенных сервисов
     */
    protected static function getAllEnabledServices(): array
    {
        $activeServices = AiService::where('is_active', true)->get();
        $services = [];
        
        foreach ($activeServices as $activeService) {
            try {
                $services[] = self::createServiceInstance($activeService);
            } catch (\Throwable $e) {
                Log::error('Ошибка инициализации сервиса', [
                    'service_id' => $activeService->id,
                    'service_name' => $activeService->name,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        Log::debug('Используем все активные сервисы', [
            'count' => count($services),
            'service_names' => array_map(fn($s) => $s::getName(), $services)
        ]);
        
        return $services;
    }

    /**
     * Получение сервиса по ID с проверкой активности
     */
    public static function getServiceById(int $serviceId): ?AiServices
    {
        try {
            $serviceModel = AiService::find($serviceId);
            
            if (!$serviceModel) {
                Log::warning('Сервис не найден', ['service_id' => $serviceId]);
                return null;
            }
            
            if (!$serviceModel->is_active) {
                Log::warning('Сервис отключен', [
                    'service_id' => $serviceId,
                    'service_name' => $serviceModel->name
                ]);
                return null;
            }
            
            return self::createServiceInstance($serviceModel);
            
        } catch (\Throwable $e) {
            Log::error('Ошибка получения сервиса по ID', [
                'service_id' => $serviceId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    // Остальные методы остаются без изменений...
    public static function getAllServices(): array
    {
        Log::info('Scanning AI services directory');
        
        $services = [];
        $dbServices = AiService::all()->keyBy('name');

        foreach (self::getServiceClasses() as $className) {
            try {
                $reflection = new ReflectionClass($className);
                $serviceInstance = $reflection->newInstanceWithoutConstructor();
                
                $serviceName = $serviceInstance->getName();
                
                $services[] = [
                    'class' => $className,
                    'name' => $serviceName,
                    'required_settings' => $serviceInstance->getRequiredSettings(),
                    'db_record' => $dbServices->get($serviceName),
                    'status' => $dbServices->has($serviceName) ? 'registered' : 'not_configured'
                ];

                Log::debug('Discovered AI service', ['service' => $serviceName]);

            } catch (\Throwable $e) {
                Log::error('Service discovery error', [
                    'class' => $className,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $services;
    }

    private static function getServiceClasses(): array
    {
        $servicesPath = app_path('Helpers/Ai/Services');
        $files = scandir($servicesPath);
        $classes = [];

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') continue;
            
            $className = pathinfo($file, PATHINFO_FILENAME);
            $fullClassName = "App\\Helpers\\Ai\\Services\\$className";
            
            if (self::isValidServiceClass($fullClassName)) {
                $classes[] = $fullClassName;
            }
        }

        return $classes;
    }

    private static function isValidServiceClass(string $className): bool
    {
        try {
            $reflection = new ReflectionClass($className);
            return $reflection->isSubclassOf(AiServices::class) 
                && !$reflection->isAbstract();
        } catch (\Throwable $e) {
            Log::warning('Invalid service class', [
                'class' => $className,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private static function createServiceInstance(AiService $serviceModel): AiServices
    {
        $className = "App\\Helpers\\Ai\\Services\\{$serviceModel->name}Service";
        
        if (!class_exists($className)) {
            throw new \RuntimeException("Service class {$className} not found");
        }

        if (!is_subclass_of($className, AiServices::class)) {
            throw new \RuntimeException("Service {$className} must extend ".AiServices::class);
        }

        return new $className($serviceModel->settings ?? []);
    }

     /**
     * Получение всех доступных функций для сервиса
     */
    public static function getServiceFeatures(int $serviceId): array
    {
        $service = self::getServiceById($serviceId);
        
        if (!$service) {
            return [];
        }

        return $service::getAvailableFeatures();
    }

    /**
     * Вызов функции сервиса
     */
    public static function callServiceFeature(int $serviceId, string $featureName, array $parameters = []): array
    {
        $service = self::getServiceById($serviceId);
        
        if (!$service) {
            return [
                'success' => false,
                'error' => "Сервис с ID {$serviceId} не найден или не активен",
            ];
        }

        return $service->callFeature($featureName, $parameters);
    }

    /**
     * Получение информации о сервисе с функциями
     */
    public static function getServiceInfo(int $serviceId): array
    {
        $serviceModel = AiService::find($serviceId);
        
        if (!$serviceModel) {
            return [];
        }

        $service = self::getServiceById($serviceId);
        
        if (!$service) {
            return [];
        }

        return [
            'id' => $serviceModel->id,
            'name' => $serviceModel->name,
            'is_active' => $serviceModel->is_active,
            'settings' => $serviceModel->settings,
            'features' => $service::getAvailableFeatures(),
            'capabilities' => [
                'embeddings' => $service->supportEmbeddings(),
                'regulars' => $service->supportRegulars(),
            ]
        ];
    }
}