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

HTML_DIR="/var/www/html"
DIST_DIR="/var/www/html.dist"
COPY_DIR="/var/www/html.copy"

log "=== СТАРТ ИНИЦИАЛИЗАЦИИ ==="

# -----------------------
# Проверка состояния проекта
# -----------------------
HAS_ARTISAN="нет"; [ -f "$HTML_DIR/artisan" ] && HAS_ARTISAN="да"
HAS_VENDOR="нет";  [ -d "$HTML_DIR/vendor" ] && HAS_VENDOR="да"

if [ "$HAS_ARTISAN" = "да" ] && [ "$HAS_VENDOR" = "да" ]; then
    MODE="optimize"
    log "✅ Режим: optimize (проект готов)"
elif [ "$HAS_ARTISAN" = "да" ]; then
    MODE="install"
    log "🔧 Режим: install (установка зависимостей)"
else
    MODE="setup"
    log "🚀 Режим: setup (установка проекта)"
fi

# -----------------------
# SETUP: установка проекта
# -----------------------
if [ "$MODE" = "setup" ]; then
    log "📦 Копируем Laravel из /var/www/html.dist..."
    cp -ra "$DIST_DIR"/* "$HTML_DIR"/ 2>/dev/null || true
    cp -ra "$DIST_DIR"/.[!.]* "$HTML_DIR"/ 2>/dev/null || true

    # Скачиваем код из репозитория
    log "🌐 Скачиваем код из Gitflic..."
    TEMP_ZIP="/tmp/repo.zip"
    TEMP_EXTRACT="/tmp/repo_extract"
    if curl -L "https://gitflic.ru/project/imperor/client/file/downloadAll?branch=master&format=zip" -o "$TEMP_ZIP" 2>/dev/null; then
        mkdir -p "$TEMP_EXTRACT"
        unzip -q "$TEMP_ZIP" -d "$TEMP_EXTRACT" 2>/dev/null && \
        CODE_DIR="$TEMP_EXTRACT/code"
        if [ -d "$CODE_DIR" ]; then
            rsync -a --delete "$CODE_DIR/" "$HTML_DIR/"
            log "✅ Код из репозитория применён"
        fi
        rm -rf "$TEMP_ZIP" "$TEMP_EXTRACT"
    fi

    # Создаём .env из .env.example
    if [ ! -f "$HTML_DIR/.env" ] && [ -f "$HTML_DIR/.env.example" ]; then
        cp "$HTML_DIR/.env.example" "$HTML_DIR/.env"
        log "✅ .env создан из .env.example"
    fi
fi

# -----------------------
# INSTALL: зависимости и миграции
# -----------------------
if [ "$MODE" = "install" ] || [ "$MODE" = "setup" ]; then
    # Composer install (только если vendor отсутствует)
    if [ ! -d "$HTML_DIR/vendor" ] && [ -f "$HTML_DIR/composer.json" ]; then
        log "📥 Выполняем composer install..."
        composer install --no-dev --no-interaction --optimize-autoloader
    fi

    # Миграции
    if [ -f "$HTML_DIR/artisan" ]; then
        log "🗃 Выполняем миграции..."
        php artisan migrate --force
    fi

    # Сборка фронтенда
    if [ -f "$HTML_DIR/package.json" ]; then
        log "🎨 Устанавливаем npm зависимости..."
        npm install --silent

        log "🔨 Собираем фронтенд (npm run build)..."
        npm run build

        # Удаляем hot — он НЕ нужен в production!
        rm -f "$HTML_DIR/public/hot"
        log "🧹 public/hot удалён"
    fi
fi

# -----------------------
# Обязательные директории и права
# -----------------------
mkdir -p "$HTML_DIR/storage/framework/cache" \
         "$HTML_DIR/storage/framework/sessions" \
         "$HTML_DIR/storage/framework/views" \
         "$HTML_DIR/storage/logs" \
         "$HTML_DIR/bootstrap/cache" \
         "$HTML_DIR/public/build"

chown -R www-data:www-data "$HTML_DIR/storage" "$HTML_DIR/bootstrap/cache" "$HTML_DIR/public/build"
chmod -R 775 "$HTML_DIR/storage" "$HTML_DIR/bootstrap/cache" "$HTML_DIR/public"

# -----------------------
# OPTIMIZE: кэширование (только если vendor существует)
# -----------------------
if [ "$MODE" = "optimize" ]; then
    log "⚡ Оптимизация Laravel..."
    php artisan config:clear
    php artisan view:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

log "✅ Инициализация завершена. Запуск PHP-FPM..."
exec php-fpm