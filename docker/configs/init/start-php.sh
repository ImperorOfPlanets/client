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
# Очистка кэша (для безопасности)
# -----------------------
log "Очистка кэша Laravel..."
php artisan cache:clear || log "⚠️  Предупреждение: cache:clear"

# -----------------------
# Копирование базовых файлов (default)
# -----------------------
if [ "$MODE" = "default" ]; then
    if [ "$IS_EMPTY" = "да" ]; then
        log "📂 Папка /var/www/html пуста, копирование Laravel..."
        cp -ra /var/www/html.dist/* /var/www/html/ 2>/dev/null || log "⚠️ Ошибка копирования файлов"
        cp -ra /var/www/html.dist/.[!.]* /var/www/html/ 2>/dev/null || true

        if [ -n "$DIR_COPY" ] && [ -d "/var/www/html.copy" ]; then
            log "📝 Добавление кастомных файлов из $DIR_COPY..."
            rsync -a --ignore-existing /var/www/html.copy/ /var/www/html/
        fi
    else
        log "📂 Файлы обнаружены, пропускаем копирование Laravel"
    fi
fi

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
fi

APP_KEY=$(grep -E '^APP_KEY=' "$ENV_FILE" | cut -d '=' -f2)
if [ -z "$APP_KEY" ]; then
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
    composer install --no-interaction --optimize-autoloader || log "⚠️ Ошибка composer install"

    log "📦 Установка NPM пакетов..."
    [ ! -d node_modules ] && npm install --quiet

    log "🛠 Сборка фронтенда..."
    npm run build || log "⚠️ Ошибка сборки фронтенда"

    log "🗃 Выполнение миграций..."
    php artisan migrate --force || log "⚠️ Ошибка миграций"
fi

# -----------------------
# Artisan кэширование
# -----------------------
php artisan config:cache || log "⚠️ Ошибка config:cache"
php artisan route:cache || log "⚠️ Ошибка route:cache"
php artisan view:cache || log "⚠️ Ошибка view:cache"

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
