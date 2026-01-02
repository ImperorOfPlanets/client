#!/bin/sh

# -----------------------
# Настройка логов
# -----------------------
LOG_FILE="/var/log/php/init.log"
mkdir -p /var/log/php
touch "$LOG_FILE"
chown www-data:www-data "$LOG_FILE"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# -----------------------
# Мини-отчёт о состоянии
# -----------------------
HTML_DIR="/var/www/html"
MODE=${INIT_MODE:-"full"}
IS_EMPTY="нет"
[ -z "$(ls -A $HTML_DIR 2>/dev/null)" ] && IS_EMPTY="да"

log "=== Старт инициализации проекта ==="
log "📂 /var/www/html пустая: $IS_EMPTY"
log "ℹ️ Режим работы: $MODE"

# -----------------------
# Проверка и копирование Laravel из образа (если папка пустая)
# -----------------------
if [ "$IS_EMPTY" = "да" ]; then
    log "📂 Папка /var/www/html пуста"
    log "🔄 Проверяем наличие Laravel в образе..."
    
    if [ -d "/var/www/html.dist" ] && [ -f "/var/www/html.dist/artisan" ]; then
        log "✅ Laravel найден в образе (в /var/www/html.dist)"
        log "📦 Копируем Laravel ИЗ ОБРАЗА в папку /var/www/html..."
        
        cp -ra /var/www/html.dist/* /var/www/html/ 2>/dev/null || log "⚠️ Ошибка копирования файлов"
        cp -ra /var/www/html.dist/.[!.]* /var/www/html/ 2>/dev/null || true
        
        # Проверяем результат
        if [ -f "/var/www/html/artisan" ]; then
            log "✅ УСПЕХ: Laravel скопирован из образа Docker"
            log "   Источник: /var/www/html.dist (внутри Docker образа)"
            log "   Назначение: /var/www/html (volume → папка code на хосте)"
        else
            log "❌ ОШИБКА: Не удалось скопировать Laravel"
        fi
        
        # Дополнительное копирование из DIR_COPY если указано
        if [ -n "$DIR_COPY" ] && [ -d "/var/www/html.copy" ]; then
            log "📝 Добавление кастомных файлов из $DIR_COPY..."
            rsync -a --ignore-existing /var/www/html.copy/ /var/www/html/
        fi
    else
        log "❌ ОШИБКА: Laravel не найден в образе Docker"
        log "   Проверьте шаг 5 в Dockerfile"
    fi
else
    log "📂 Файлы обнаружены в /var/www/html"
    if [ -f "$HTML_DIR/artisan" ]; then
        log "✅ Laravel уже установлен"
    else
        log "⚠️  В папке есть файлы, но artisan не найден"
    fi
fi

# -----------------------
# Очистка кэша (для безопасности)
# -----------------------
log "Очистка кэша Laravel..."
php artisan cache:clear 2>/dev/null || log "⚠️  Предупреждение: cache:clear (artisan может быть недоступен)"

# -----------------------
# Создание структуры директорий
# -----------------------
log "Создание структуры каталогов..."
DIRS="
$HTML_DIR/storage/framework/cache
$HTML_DIR/storage/framework/sessions
$HTML_DIR/storage/framework/views
$HTML_DIR/storage/logs
$HTML_DIR/bootstrap/cache
"

for dir in $DIRS; do
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir"
        log "✅ Создана директория: $dir"
    else
        log "ℹ️ Директория уже существует: $dir"
    fi
done

log "Настройка прав доступа..."
chown -R www-data:www-data $HTML_DIR/storage $HTML_DIR/bootstrap/cache
find $HTML_DIR/storage -type d -exec chmod 775 {} \;
find $HTML_DIR/storage -type f -exec chmod 664 {} \;
find $HTML_DIR/bootstrap/cache -type d -exec chmod 775 {} \;
find $HTML_DIR/bootstrap/cache -type f -exec chmod 664 {} \;

# -----------------------
# Настройка .env и APP_KEY
# -----------------------
ENV_FILE="$HTML_DIR/.env"
if [ ! -f "$ENV_FILE" ] && [ -f "$ENV_FILE.example" ]; then
    cp "$ENV_FILE.example" "$ENV_FILE"
    log "✅ .env создан из .env.example"
elif [ ! -f "$ENV_FILE" ]; then
    log "⚠️  .env.example не найден, создаем базовый .env"
    cat > "$ENV_FILE" <<EOF
APP_NAME=Laravel
APP_ENV=${APP_ENV:-local}
APP_KEY=
APP_DEBUG=${APP_DEBUG:-true}
APP_URL=${APP_URL:-http://localhost}

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=${DB_CONNECTION:-mysql}
DB_HOST=${DB_HOST:-192.168.0.185}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_DATABASE:-laravel}
DB_USERNAME=${DB_USERNAME:-root}
DB_PASSWORD=${DB_PASSWORD:-}

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120
EOF
    log "✅ Базовый .env создан"
fi

APP_KEY=$(grep -E '^APP_KEY=' "$ENV_FILE" 2>/dev/null | cut -d '=' -f2 || true)
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "null" ] || [ "$APP_KEY" = "" ]; then
    APP_KEY="base64:$(openssl rand -base64 32)"
    export APP_KEY
    log "🔑 Сгенерирован новый APP_KEY: $APP_KEY"
else
    log "🔑 APP_KEY уже установлен: $APP_KEY"
fi

update_env_var() {
    KEY=$1
    VALUE=$2
    if grep -q "^$KEY=" "$ENV_FILE"; then
        sed -i "s|^$KEY=.*|$KEY=$VALUE|" "$ENV_FILE"
    else
        echo "$KEY=$VALUE" >> "$ENV_FILE"
    fi
}

update_env_var "APP_ENV" "${APP_ENV:-production}"
update_env_var "APP_KEY" "$APP_KEY"
update_env_var "APP_DEBUG" "${APP_DEBUG:-false}"
update_env_var "APP_URL" "${APP_URL:-http://localhost}"
update_env_var "DB_CONNECTION" "${DB_CONNECTION:-mysql}"
update_env_var "DB_HOST" "${DB_HOST:-db}"
update_env_var "DB_PORT" "${DB_PORT:-3306}"
update_env_var "DB_DATABASE" "${DB_DATABASE:-laravel}"
update_env_var "DB_USERNAME" "${DB_USERNAME:-root}"
update_env_var "DB_PASSWORD" "${DB_PASSWORD:-}"

# -----------------------
# Проверка подключения к БД
# -----------------------
log "=== Проверка подключения к БД ==="
check_db() {
    ATTEMPTS=3
    i=1
    while [ $i -le $ATTEMPTS ]; do
        log "Попытка $i: подключение к $DB_HOST:$DB_PORT..."
        
        if mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" -e "SELECT 1" >/dev/null 2>&1; then
            log "✅ Успешное подключение к базе"
            return 0
        else
            log "❌ Не удалось подключиться к базе"
        fi
        
        sleep 2
        i=$((i+1))
    done
    return 1
}

if ! check_db; then
    log "🛑 Критическая ошибка: база недоступна!"
    # exit 1
fi

# -----------------------
# Composer, NPM, сборка, миграции (только default)
# -----------------------
if [ "$MODE" = "default" ]; then
    log "💻 Установка PHP зависимостей..."
    composer install --no-interaction --optimize-autoloader 2>/dev/null || log "⚠️ Ошибка composer install (возможно vendor уже установлен)"
    
    # Проверяем наличие composer.json
    if [ -f "$HTML_DIR/composer.json" ]; then
        log "📦 Установка NPM пакетов..."
        [ ! -d "$HTML_DIR/node_modules" ] && npm install --quiet 2>/dev/null || log "⚠️ Ошибка npm install"
        
        log "🛠 Сборка фронтенда..."
        npm run build 2>/dev/null || log "⚠️ Ошибка сборки фронтенда (возможно package.json не настроен)"
        
        log "🗃 Выполнение миграций..."
        php artisan migrate --force 2>/dev/null || log "⚠️ Ошибка миграций (возможно база не готова)"
    else
        log "⚠️  composer.json не найден, пропускаем установку зависимостей"
    fi
fi

# -----------------------
# Artisan кэширование (только если artisan доступен)
# -----------------------
if [ -f "$HTML_DIR/artisan" ]; then
    php artisan config:cache 2>/dev/null || log "⚠️ Ошибка config:cache"
    php artisan route:cache 2>/dev/null || log "⚠️ Ошибка route:cache"
    php artisan view:cache 2>/dev/null || log "⚠️ Ошибка view:cache"
else
    log "⚠️  artisan не найден, пропускаем кэширование"
fi

# -----------------------
# Экспорт всех переменных из .env
# -----------------------
if [ -f "$HTML_DIR/.env" ]; then
    log "Экспорт переменных из .env"
    set -a
    . "$HTML_DIR/.env"
    set +a
fi

log "=== Инициализация завершена, запускаем php-fpm ==="
exec php-fpm