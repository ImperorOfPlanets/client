#!/bin/bash

echo "=== Starting Laravel Reverb ==="

# Переходим в директорию с Laravel
cd /var/www/reverb-app

echo "Working directory: $(pwd)"
echo "Laravel version:"
php artisan --version

echo "Starting Reverb on 0.0.0.0:8443..."
echo "Hostname: client.myidon.site"

# Запускаем в ФОРЕГРАУНДЕ (без & в конце!)
exec php artisan reverb:start \
    --host=0.0.0.0 \
    --port=8443 \
    --hostname=client.myidon.site \
    --debug