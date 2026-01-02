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
# Определение режима работы
# -----------------------
HTML_DIR="/var/www/html"
IS_EMPTY="нет"
[ -z "$(ls -A $HTML_DIR 2>/dev/null)" ] && IS_EMPTY="да"

# Проверяем наличие vendor и artisan
HAS_ARTISAN="нет"
HAS_VENDOR="нет"
[ -f "$HTML_DIR/artisan" ] && HAS_ARTISAN="да"
[ -d "$HTML_DIR/vendor" ] && HAS_VENDOR="да"

# Определяем режим автоматически
if [ "$HAS_ARTISAN" = "да" ] && [ "$HAS_VENDOR" = "да" ]; then
    AUTO_MODE="optimize"
    log "✅ Обнаружены artisan и vendor - режим: optimize"
elif [ "$HAS_ARTISAN" = "да" ]; then
    AUTO_MODE="partial"
    log "⚠️  Обнаружен artisan, но нет vendor - режим: partial"
else
    AUTO_MODE="full"
    log "❌ Не обнаружены artisan и vendor - режим: full"
fi

# Приоритет: ручной режим или автоматический
MODE=${INIT_MODE:-$AUTO_MODE}

log "=== Старт инициализации проекта ==="
log "📂 /var/www/html пустая: $IS_EMPTY"
log "🔍 Наличие artisan: $HAS_ARTISAN"
log "🔍 Наличие vendor: $HAS_VENDOR"
log "🤖 Автоопределенный режим: $AUTO_MODE"
log "🎯 Финальный режим работы: $MODE"

# -----------------------
# Режим FULL: полная установка
# -----------------------
if [ "$MODE" = "full" ]; then
    log "🚀 Запуск ПОЛНОЙ установки..."
    
    # 1. Копируем Laravel из образа если папка пуста
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
            else
                log "❌ ОШИБКА: Не удалось скопировать Laravel"
            fi
        else
            log "❌ ОШИБКА: Laravel не найден в образе Docker"
        fi
    fi
    
    # 2. Дополнительное копирование из DIR_COPY если указано
    if [ -n "$DIR_COPY" ] && [ -d "/var/www/html.copy" ]; then
        log "📝 Добавление кастомных файлов из $DIR_COPY..."
        rsync -a --ignore-existing /var/www/html.copy/ /var/www/html/
    fi
    
    # 3. Скачивание и распаковка кода из репозитория
    log "🌐 Скачивание кода из репозитория..."
    TEMP_ZIP="/tmp/repo.zip"
    TEMP_EXTRACT="/tmp/repo_extract"
    
    # Скачиваем архив
    if curl -L "https://gitflic.ru/project/imperor/client/file/downloadAll?branch=master&format=zip" \
        -o "$TEMP_ZIP" 2>/dev/null; then
        log "✅ Архив успешно скачан"
        
        # Создаем временную папку для распаковки
        mkdir -p "$TEMP_EXTRACT"
        
        # Распаковываем архив
        if unzip -q "$TEMP_ZIP" -d "$TEMP_EXTRACT" 2>/dev/null; then
            log "✅ Архив распакован"
            
            # Ищем папку code в распакованном содержимом
            CODE_DIR=$(find "$TEMP_EXTRACT" -type d -name "code" | head -1)
            
            if [ -n "$CODE_DIR" ] && [ -d "$CODE_DIR" ]; then
                log "📁 Найдена папка code в архиве"
                log "📦 Копируем содержимое папки code в /var/www/html..."
                
                # Копируем с сохранением прав
                cp -ra "$CODE_DIR"/* "$HTML_DIR"/ 2>/dev/null || log "⚠️ Ошибка копирования из code"
                cp -ra "$CODE_DIR"/.[!.]* "$HTML_DIR"/ 2>/dev/null 2>/dev/null || true
                
                log "✅ Код из репозитория успешно скопирован"
            else
                log "⚠️ Папка code не найдена в архиве, копируем все содержимое"
                cp -ra "$TEMP_EXTRACT"/* "$HTML_DIR"/ 2>/dev/null || log "⚠️ Ошибка копирования всего содержимого"
                cp -ra "$TEMP_EXTRACT"/.[!.]* "$HTML_DIR"/ 2>/dev/null 2>/dev/null || true
            fi
        else
            log "❌ Ошибка распаковки архива"
        fi
        
        # Очистка временных файлов
        rm -rf "$TEMP_ZIP" "$TEMP_EXTRACT"
    else
        log "❌ Ошибка скачивания архива из репозитория"
    fi
    
    # 4. Проверяем, появился ли artisan после всех копирований
    if [ ! -f "$HTML_DIR/artisan" ]; then
        log "🛑 КРИТИЧЕСКАЯ ОШИБКА: artisan не найден после всех операций!"
        log "   Проверьте источники данных (образ, DIR_COPY, репозиторий)"
    fi

# -----------------------
# Режим PARTIAL: есть artisan, но нет vendor
# -----------------------
elif [ "$MODE" = "partial" ]; then
    log "🔧 Запуск ЧАСТИЧНОЙ установки (только зависимости и миграции)..."
    
    # Проверяем наличие composer.json
    if [ ! -f "$HTML_DIR/composer.json" ]; then
        log "❌ ОШИБКА: composer.json не найден в режиме partial"
        log "   Переключаемся в режим full"
        MODE="full"
        # Здесь можно добавить повторный вызов full логики или выйти
    fi

# -----------------------
# Режим OPTIMIZE: есть и artisan и vendor
# -----------------------
elif [ "$MODE" = "optimize" ]; then
    log "⚡ Запуск ОПТИМИЗАЦИИ (кеширование и запуск)..."
    
    # Проверяем что artisan действительно доступен
    if [ ! -f "$HTML_DIR/artisan" ]; then
        log "❌ ОШИБКА: artisan не найден в режиме optimize"
        exit 1
    fi
fi

# -----------------------
# ОБЩИЕ ДЕЙСТВИЯ для всех режимов (кроме optimize)
# -----------------------
if [ "$MODE" != "optimize" ]; then
    # Очистка кэша (для безопасности)
    log "Очистка кэша Laravel..."
    [ -f "$HTML_DIR/artisan" ] && php artisan cache:clear 2>/dev/null || log "⚠️  Предупреждение: cache:clear"

    # Создание структуры директорий
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
fi

# -----------------------
# Настройка .env и APP_KEY (для всех режимов)
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

# Обновляем основные переменные
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
# ДЕЙСТВИЯ ДЛЯ РЕЖИМОВ full и partial
# -----------------------
if [ "$MODE" = "full" ] || [ "$MODE" = "partial" ]; then
    # Установка зависимостей Composer
    log "💻 Установка PHP зависимостей..."
    if [ -f "$HTML_DIR/composer.json" ]; then
        composer install --no-interaction --optimize-autoloader 2>&1 | tee -a "$LOG_FILE" | tail -5
        log "✅ Composer зависимости установлены"
    else
        log "❌ ОШИБКА: composer.json не найден"
    fi
    
    # Установка NPM пакетов
    if [ -f "$HTML_DIR/package.json" ]; then
        log "📦 Установка NPM пакетов..."
        [ ! -d "$HTML_DIR/node_modules" ] && npm install --quiet 2>&1 | tee -a "$LOG_FILE" | tail -5
        log "✅ NPM зависимости установлены"
        
        log "🛠 Сборка фронтенда..."
        npm run build 2>&1 | tee -a "$LOG_FILE" | tail -5
        log "✅ Фронтенд собран"
    else
        log "⚠️  package.json не найден, пропускаем сборку фронтенда"
    fi
    
    # Выполнение миграций с детальным отчетом
    if [ -f "$HTML_DIR/artisan" ]; then
        log "🗃 Выполнение миграций (с отчетом по каждой)..."
        
        # Получаем список всех миграций
        MIGRATIONS=$(php artisan migrate:status --database=mysql 2>/dev/null | grep "| No" | awk '{print $2}' | head -20)
        
        if [ -n "$MIGRATIONS" ]; then
            log "📋 Найдены невыполненные миграции:"
            for migration in $MIGRATIONS; do
                log "   - $migration"
            done
            
            # Выполняем миграции по одной
            for migration in $MIGRATIONS; do
                log "🔄 Выполнение миграции: $migration"
                if php artisan migrate --step=1 --force 2>&1 | tee -a "$LOG_FILE" | tail -3; then
                    log "✅ Миграция успешно выполнена: $migration"
                else
                    log "⚠️  Пропуск миграции с ошибкой: $migration"
                    # Пропускаем проблемную миграцию и продолжаем
                    continue
                fi
            done
        else
            log "ℹ️  Все миграции уже выполнены или их нет"
        fi
    fi
fi

# -----------------------
# Artisan оптимизация и кэширование (для всех режимов, где есть artisan)
# -----------------------
if [ -f "$HTML_DIR/artisan" ]; then
    log "⚡ Оптимизация Laravel..."
    
    # Кэширование конфигурации
    php artisan config:cache 2>&1 | tee -a "$LOG_FILE" | tail -2
    log "✅ Конфигурация закэширована"
    
    # Кэширование роутов
    php artisan route:cache 2>&1 | tee -a "$LOG_FILE" | tail -2
    log "✅ Роуты закэшированы"
    
    # Кэширование вьюх
    php artisan view:cache 2>&1 | tee -a "$LOG_FILE" | tail -2
    log "✅ Вьюхи закэшированы"
    
    # Оптимизация загрузчика
    composer dump-autoload --optimize 2>&1 | tee -a "$LOG_FILE" | tail -2
    log "✅ Автозагрузчик оптимизирован"
else
    log "⚠️  artisan не найден, пропускаем оптимизацию"
fi

# -----------------------
# Экспорт всех переменных из .env (КАК В СТАРОЙ ВЕРСИИ)
# -----------------------
if [ -f "$HTML_DIR/.env" ]; then
    log "📝 Экспорт переменных из .env"
    # Просто загружаем .env файл как в старой версии
    set -a
    . "$HTML_DIR/.env"
    set +a
    log "✅ Переменные .env экспортированы"
fi

log "=== Инициализация завершена в режиме [$MODE] ==="
log "🚀 Запускаем php-fpm..."

exec php-fpm