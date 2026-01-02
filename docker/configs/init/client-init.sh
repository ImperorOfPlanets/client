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
DIST_DIR="/var/www/html.dist"
COPY_DIR="/var/www/html.copy"

log "=== СТАРТ ИНИЦИАЛИЗАЦИИ PHP-КОНТЕЙНЕРА ==="
log "📂 Рабочая директория: $HTML_DIR"
log "📦 Образ Laravel: $DIST_DIR"
log "📝 Доп. файлы: $COPY_DIR"

# Детальная проверка содержимого volume
log "🔍 Проверка содержимого shared-code volume..."
log "Список файлов в $HTML_DIR:"

# Проверяем есть ли хоть что-то в папке
if [ -n "$(ls -1qA "$HTML_DIR" 2>/dev/null)" ]; then
    ls -la "$HTML_DIR" 2>/dev/null | while read line; do 
        log "   $line"; 
    done
else
    log "   📁 Папка ПУСТА"
fi

# Проверяем ключевые файлы
HAS_ARTISAN="нет"
HAS_COMPOSER_JSON="нет"
HAS_VENDOR="нет"
HAS_ENV="нет"
HAS_ENV_EXAMPLE="нет"

if [ -f "$HTML_DIR/artisan" ]; then HAS_ARTISAN="да" && log "   ✅ artisan найден"; fi
if [ -f "$HTML_DIR/composer.json" ]; then HAS_COMPOSER_JSON="да" && log "   ✅ composer.json найден"; fi
if [ -d "$HTML_DIR/vendor" ]; then HAS_VENDOR="да" && log "   ✅ vendor найден"; fi
if [ -f "$HTML_DIR/.env" ]; then HAS_ENV="да" && log "   ✅ .env найден"; fi
if [ -f "$HTML_DIR/.env.example" ]; then HAS_ENV_EXAMPLE="да" && log "   ✅ .env.example найден"; fi

# Определяем режим работы
log "📊 Анализ состояния проекта:"
log "   artisan: $HAS_ARTISAN"
log "   composer.json: $HAS_COMPOSER_JSON"
log "   vendor: $HAS_VENDOR"
log "   .env: $HAS_ENV"
log "   .env.example: $HAS_ENV_EXAMPLE"

if [ "$HAS_ARTISAN" = "да" ]; then
    if [ "$HAS_VENDOR" = "да" ]; then
        AUTO_MODE="optimize"
        log "⚡ Авторежим: OPTIMIZE (проект готов)"
    else
        AUTO_MODE="install"
        log "🔧 Авторежим: INSTALL (нужны зависимости)"
    fi
else
    AUTO_MODE="setup"
    log "🚀 Авторежим: SETUP (нужна установка проекта)"
fi

# Приоритет: ручной режим или автоматический
if [ -n "$INIT_MODE" ]; then
    MODE="$INIT_MODE"
else
    MODE="$AUTO_MODE"
fi

log "🎯 Финальный режим работы: $MODE"

# -----------------------
# Режим SETUP: установка/копирование проекта
# -----------------------
if [ "$MODE" = "setup" ]; then
    log "🚀 Запуск SETUP (установка проекта)..."
    
    # 1. Проверяем наличие Laravel в образе Docker
    if [ ! -f "$HTML_DIR/artisan" ] && [ -d "$DIST_DIR" ] && [ -f "$DIST_DIR/artisan" ]; then
        log "📦 Копируем чистый Laravel из Docker образа..."
        cp -ra "$DIST_DIR"/* "$HTML_DIR"/ 2>/dev/null || log "⚠️  Ошибка копирования файлов"
        cp -ra "$DIST_DIR"/.[!.]* "$HTML_DIR"/ 2>/dev/null 2>/dev/null || true
        log "✅ Laravel скопирован из образа"
    fi
    
    # 2. Пробуем скачать код из репозитория (перезапишет если было)
    log "🌐 Пробуем скачать код из репозитория Gitflic..."
    TEMP_ZIP="/tmp/repo.zip"
    TEMP_EXTRACT="/tmp/repo_extract"
    
    if curl -L "https://gitflic.ru/project/imperor/client/file/downloadAll?branch=master&format=zip" \
        -o "$TEMP_ZIP" 2>/dev/null; then
        log "✅ Архив скачан"
        
        mkdir -p "$TEMP_EXTRACT"
        if unzip -q "$TEMP_ZIP" -d "$TEMP_EXTRACT" 2>/dev/null; then
            log "✅ Архив распакован"
            
            # Ищем папку code
            CODE_DIR=""
            for dir in "$TEMP_EXTRACT"/*; do
                if [ -d "$dir" ] && [ "$(basename "$dir")" = "code" ]; then
                    CODE_DIR="$dir"
                    break
                fi
            done
            
            if [ -n "$CODE_DIR" ] && [ -d "$CODE_DIR" ]; then
                log "📁 Найдена папка 'code' в архиве"
                log "📦 Копируем содержимое папки code..."
                cp -ra "$CODE_DIR"/* "$HTML_DIR"/ 2>/dev/null || log "⚠️ Ошибка копирования из code"
                cp -ra "$CODE_DIR"/.[!.]* "$HTML_DIR"/ 2>/dev/null 2>/dev/null || true
                log "✅ Код из репозитория скопирован"
            else
                log "⚠️ Папка 'code' не найдена, копируем все содержимое архива"
                cp -ra "$TEMP_EXTRACT"/* "$HTML_DIR"/ 2>/dev/null || log "⚠️ Ошибка копирования всего содержимого"
                cp -ra "$TEMP_EXTRACT"/.[!.]* "$HTML_DIR"/ 2>/dev/null 2>/dev/null || true
            fi
        else
            log "❌ Ошибка распаковки архива"
        fi
        
        # Очистка временных файлов
        rm -rf "$TEMP_ZIP" "$TEMP_EXTRACT"
    else
        log "❌ Не удалось скачать код из репозитория"
    fi
    
    # 3. Дополнительное копирование из DIR_COPY если указано
    if [ -d "$COPY_DIR" ] && [ "$COPY_DIR" != "/var/www/html.copy" ]; then
        log "📝 Добавление файлов из DIR_COPY ($COPY_DIR)..."
        if [ -n "$(ls -A "$COPY_DIR" 2>/dev/null)" ]; then
            rsync -a --ignore-existing "$COPY_DIR"/ "$HTML_DIR"/
            log "✅ Файлы из DIR_COPY добавлены"
        else
            log "ℹ️ DIR_COPY пустая папка"
        fi
    fi
    
    # 4. Проверяем результат
    if [ -f "$HTML_DIR/artisan" ]; then
        log "✅ УСПЕХ: Проект установлен"
        # Переключаемся на установку зависимостей
        MODE="install"
    else
        log "❌ КРИТИЧЕСКАЯ ОШИБКА: Проект не установлен!"
        log "   artisan не найден после всех попыток установки"
        log "   Проверьте:"
        log "   1. Наличие Laravel в Docker образе ($DIST_DIR)"
        log "   2. Доступность репозитория Gitflic"
        log "   3. Наличие папки 'code' в архиве репозитория"
        # Продолжаем в надежде что что-то есть
    fi
fi

# -----------------------
# Режим INSTALL: установка зависимостей
# -----------------------
if [ "$MODE" = "install" ]; then
    log "🔧 Запуск INSTALL (установка зависимостей)..."
    
    # Проверяем наличие composer.json
    if [ -f "$HTML_DIR/composer.json" ]; then
        log "📦 Установка PHP зависимостей..."
        
        # Устанавливаем зависимости если vendor нет
        if [ ! -d "$HTML_DIR/vendor" ]; then
            log "🔄 Composer install..."
            composer install --no-interaction --optimize-autoloader --no-progress 2>&1 | tee -a "$LOG_FILE"
            COMPOSER_EXIT=$?
            if [ "$COMPOSER_EXIT" -eq 0 ]; then
                log "✅ Composer зависимости установлены"
            else
                log "⚠️ Composer завершился с кодом $COMPOSER_EXIT"
                # Пробуем обновить зависимости
                log "🔄 Пробуем composer update..."
                composer update --no-interaction --optimize-autoloader --no-progress 2>&1 | tee -a "$LOG_FILE"
            fi
        else
            log "✅ Vendor уже существует, пропускаем установку"
            # Обновляем автозагрузчик на всякий случай
            composer dump-autoload --optimize 2>&1 | tee -a "$LOG_FILE"
        fi
    else
        log "❌ composer.json не найден, не можем установить зависимости"
        log "   Проверьте наличие проекта в volume"
    fi
    
    # После установки зависимостей переходим к оптимизации
    MODE="optimize"
fi

# -----------------------
# ОБЯЗАТЕЛЬНОЕ СОЗДАНИЕ ДИРЕКТОРИЙ И НАСТРОЙКА ПРАВ (ДЛЯ ВСЕХ РЕЖИМОВ!)
# -----------------------
log "📁 ОБЯЗАТЕЛЬНОЕ создание структуры каталогов Laravel..."

# Список обязательных директорий
DIRS_TO_CREATE="
storage/framework/cache
storage/framework/sessions
storage/framework/views
storage/logs
bootstrap/cache
public/build
"

for dir in $DIRS_TO_CREATE; do
    FULL_DIR="$HTML_DIR/$dir"
    if [ ! -d "$FULL_DIR" ]; then
        mkdir -p "$FULL_DIR"
        log "   ✅ Создана: $dir"
    else
        log "   ℹ️ Уже существует: $dir"
    fi
done

# Проверяем и исправляем права на директории
log "🔒 ОБЯЗАТЕЛЬНАЯ настройка прав доступа..."

# Проверяем владельца основных директорий
log "👤 Проверка владельца файлов..."
CURRENT_OWNER=$(stat -c "%U:%G" "$HTML_DIR" 2>/dev/null || echo "неизвестно")
log "   Владелец $HTML_DIR: $CURRENT_OWNER"

# Устанавливаем правильного владельца для критичных директорий
log "🔄 Установка владельца www-data:www-data..."
chown -R www-data:www-data "$HTML_DIR/storage" 2>/dev/null || log "⚠️ Не удалось изменить владельца storage"
chown -R www-data:www-data "$HTML_DIR/bootstrap/cache" 2>/dev/null || log "⚠️ Не удалось изменить владельца bootstrap/cache"
chown -R www-data:www-data "$HTML_DIR/public/build" 2>/dev/null || log "⚠️ Не удалось изменить владельца public/build"

# Устанавливаем правильные права
log "🔧 Установка прав 775..."
chmod -R 775 "$HTML_DIR/storage" 2>/dev/null || log "⚠️ Не удалось установить права на storage"
chmod -R 775 "$HTML_DIR/bootstrap/cache" 2>/dev/null || log "⚠️ Не удалось установить права на bootstrap/cache"
chmod -R 775 "$HTML_DIR/public/build" 2>/dev/null || log "⚠️ Не удалось установить права на public/build"
chmod -R 775 "$HTML_DIR/public" 2>/dev/null || log "⚠️ Не удалось установить права на public"

# Дополнительная проверка и установка прав на все файлы проекта
log "📋 Проверка прав доступа к ключевым файлам..."
if [ -f "$HTML_DIR/artisan" ]; then
    chmod 755 "$HTML_DIR/artisan" 2>/dev/null || log "⚠️ Не удалось установить права на artisan"
    log "   ✅ Права на artisan установлены"
fi

# Проверяем, что PHP-FPM может писать в критические директории
log "🔍 Проверка возможности записи..."
if sudo -u www-data touch "$HTML_DIR/storage/framework/views/test_write" 2>/dev/null; then
    rm -f "$HTML_DIR/storage/framework/views/test_write"
    log "✅ PHP-FPM может писать в storage/framework/views"
else
    log "❌ PHP-FPM НЕ может писать в storage/framework/views"
    # Принудительно меняем права рекурсивно
    find "$HTML_DIR/storage" -type d -exec chmod 775 {} \; 2>/dev/null || true
    find "$HTML_DIR/storage" -type f -exec chmod 664 {} \; 2>/dev/null || true
    find "$HTML_DIR/bootstrap/cache" -type d -exec chmod 775 {} \; 2>/dev/null || true
    find "$HTML_DIR/bootstrap/cache" -type f -exec chmod 664 {} \; 2>/dev/null || true
    find "$HTML_DIR/public" -type d -exec chmod 775 {} \; 2>/dev/null || true
    find "$HTML_DIR/public" -type f -exec chmod 664 {} \; 2>/dev/null || true
    log "🔄 Права принудительно исправлены"
fi

log "✅ Права доступа гарантированно настроены"

# -----------------------
# Настройка .env файла
# -----------------------
ENV_FILE="$HTML_DIR/.env"
ENV_EXAMPLE="$HTML_DIR/.env.example"

log "⚙️  Работа с .env файлом..."

# Если .env не существует
if [ ! -f "$ENV_FILE" ]; then
    # Пробуем скопировать из .env.example
    if [ -f "$ENV_EXAMPLE" ]; then
        cp "$ENV_EXAMPLE" "$ENV_FILE"
        log "✅ .env создан из .env.example"
    else
        # Создаем базовый .env
        log "📄 Создаем базовый .env..."
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
DB_HOST=${DB_HOST:-mariadb-${PROJECTNAME}}
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

# Дополнительные переменные
QDRANT_HOST=${QDRANT_HOST:-http://qdrant-${PROJECTNAME}:6333}
QDRANT_COLLECTION=${QDRANT_COLLECTION:-embeddings}
REDIS_HOST=redis-${PROJECTNAME}
REDIS_PORT=6379
EOF
        log "✅ Базовый .env создан"
    fi
else
    log "ℹ️ .env уже существует"
fi

# Устанавливаем права на .env файл
if [ -f "$ENV_FILE" ]; then
    chown www-data:www-data "$ENV_FILE" 2>/dev/null || true
    chmod 640 "$ENV_FILE" 2>/dev/null || true
    log "✅ Права на .env установлены"
fi

# Генерация APP_KEY если не установлен
APP_KEY=$(grep -E '^APP_KEY=' "$ENV_FILE" 2>/dev/null | cut -d '=' -f2 || true)
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "null" ] || [ "$APP_KEY" = "" ]; then
    APP_KEY="base64:$(openssl rand -base64 32)"
    log "🔑 Сгенерирован новый APP_KEY"
else
    log "🔑 APP_KEY уже установлен"
fi

# Функция обновления переменных в .env
update_env_var() {
    KEY=$1
    VALUE=$2
    if grep -q "^$KEY=" "$ENV_FILE"; then
        sed -i "s|^$KEY=.*|$KEY=$VALUE|" "$ENV_FILE" 2>/dev/null || true
    else
        echo "$KEY=$VALUE" >> "$ENV_FILE"
    fi
}

# Обновляем основные переменные из окружения Docker
log "🔄 Обновление переменных в .env..."
update_env_var "APP_ENV" "${APP_ENV:-local}"
update_env_var "APP_KEY" "$APP_KEY"
update_env_var "APP_DEBUG" "${APP_DEBUG:-true}"
update_env_var "APP_URL" "${APP_URL:-http://localhost}"

# Database
update_env_var "DB_CONNECTION" "${DB_CONNECTION:-mysql}"
update_env_var "DB_HOST" "${DB_HOST:-mariadb-${PROJECTNAME}}"
update_env_var "DB_PORT" "${DB_PORT:-3306}"
update_env_var "DB_DATABASE" "${DB_DATABASE:-laravel}"
update_env_var "DB_USERNAME" "${DB_USERNAME:-root}"
update_env_var "DB_PASSWORD" "${DB_PASSWORD:-}"

# Qdrant
if [ -n "$QDRANT_HOST" ]; then update_env_var "QDRANT_HOST" "$QDRANT_HOST"; fi
if [ -n "$QDRANT_COLLECTION" ]; then update_env_var "QDRANT_COLLECTION" "$QDRANT_COLLECTION"; fi
if [ -n "$QDRANT_TIMEOUT" ]; then update_env_var "QDRANT_TIMEOUT" "$QDRANT_TIMEOUT"; fi

# Redis
update_env_var "REDIS_HOST" "redis-${PROJECTNAME}"
update_env_var "REDIS_PORT" "6379"

# Reverb
if [ -n "$REVERB_APP_ID" ]; then update_env_var "REVERB_APP_ID" "$REVERB_APP_ID"; fi
if [ -n "$REVERB_APP_KEY" ]; then update_env_var "REVERB_APP_KEY" "$REVERB_APP_KEY"; fi
if [ -n "$REVERB_APP_SECRET" ]; then update_env_var "REVERB_APP_SECRET" "$REVERB_APP_SECRET"; fi
if [ -n "$REVERB_HOST" ]; then update_env_var "REVERB_HOST" "$REVERB_HOST"; fi
if [ -n "$REVERB_PORT" ]; then update_env_var "REVERB_PORT" "$REVERB_PORT"; fi
if [ -n "$REVERB_SCHEME" ]; then update_env_var "REVERB_SCHEME" "$REVERB_SCHEME"; fi

# OAuth
if [ -n "$OAUTH_CLIENT_ID" ]; then update_env_var "OAUTH_CLIENT_ID" "$OAUTH_CLIENT_ID"; fi
if [ -n "$OAUTH_SECRET" ]; then update_env_var "OAUTH_SECRET" "$OAUTH_SECRET"; fi

# Очереди
if [ -n "$QUEUE_CONNECTION" ]; then update_env_var "QUEUE_CONNECTION" "$QUEUE_CONNECTION"; fi

# Vite assets (важно для сборки фронтенда)
update_env_var "VITE_APP_NAME" "${APP_NAME:-Laravel}"
update_env_var "VITE_APP_URL" "${APP_URL:-http://localhost}"
update_env_var "VITE_PUSHER_APP_KEY" "${REVERB_APP_KEY:-}"
update_env_var "VITE_PUSHER_HOST" "${REVERB_HOST:-localhost}"
update_env_var "VITE_PUSHER_PORT" "${REVERB_PORT:-6001}"
update_env_var "VITE_PUSHER_SCHEME" "${REVERB_SCHEME:-http}"

log "✅ .env обновлен"

# -----------------------
# Проверка и выполнение миграций
# -----------------------
if [ -f "$HTML_DIR/artisan" ]; then
    log "🗃 Работа с базой данных..."
    
    # Ждем доступности БД (максимум 60 секунд)
    log "⏳ Ожидание доступности MySQL ($DB_HOST:$DB_PORT)..."
    DB_READY=0
    i=1
    while [ $i -le 30 ]; do
        if mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" \
            -e "SELECT 1" >/dev/null 2>&1; then
            DB_READY=1
            log "✅ База данных доступна (попытка $i/30)"
            break
        fi
        log "   Попытка $i/30: база не отвечает..."
        sleep 2
        i=$((i+1))
    done
    
    if [ "$DB_READY" -eq 1 ]; then
        # Проверяем есть ли таблица миграций
        MIGRATIONS_TABLE_EXISTS=$(mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" \
            -D "$DB_DATABASE" -e "SHOW TABLES LIKE 'migrations'" 2>/dev/null | wc -l)
        
        if [ "$MIGRATIONS_TABLE_EXISTS" -gt 1 ]; then
            # Таблица существует, проверяем невыполненные миграции
            log "🔍 Проверяем статус миграций..."
            MIGRATION_OUTPUT=$(php artisan migrate:status --database=mysql 2>/dev/null || echo "Ошибка проверки миграций")
            
            if echo "$MIGRATION_OUTPUT" | grep -q "No"; then
                log "🔄 Выполняем миграции..."
                php artisan migrate --force 2>&1 | tee -a "$LOG_FILE"
                MIGRATE_EXIT=$?
                if [ "$MIGRATE_EXIT" -eq 0 ]; then
                    log "✅ Миграции выполнены успешно"
                else
                    log "⚠️ Миграции завершились с ошибкой (код: $MIGRATE_EXIT)"
                    # Пробуем выполнить по одной миграции
                    log "🔄 Пробуем выполнить миграции по одной..."
                    php artisan migrate:status --database=mysql 2>/dev/null | grep "No" | awk '{print $2}' | while read migration; do
                        log "   Выполняем: $migration"
                        php artisan migrate --path="database/migrations/$migration" --force 2>&1 | tee -a "$LOG_FILE"
                    done
                fi
            else
                log "ℹ️ Все миграции уже выполнены"
            fi
        else
            # Таблицы миграций нет, создаем базу если нужно
            log "📝 Таблица миграций не найдена, создаем базу..."
            mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" \
                -e "CREATE DATABASE IF NOT EXISTS \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" 2>/dev/null
            
            # Выполняем все миграции
            log "🔄 Выполняем начальные миграции..."
            php artisan migrate --force 2>&1 | tee -a "$LOG_FILE"
        fi
    else
        log "❌ База данных недоступна после 30 попыток"
        log "   Проверьте настройки подключения к БД"
    fi
else
    log "⚠️ artisan не найден, пропускаем миграции"
fi

# -----------------------
# ОБЯЗАТЕЛЬНАЯ СБОРКА ФРОНТЕНДА (VITE) С УСТАНОВКОЙ VITE
# -----------------------
log "🎨 ОБЯЗАТЕЛЬНАЯ сборка фронтенда (Vite)..."

# Проверяем наличие package.json и нужных скриптов
if [ -f "$HTML_DIR/package.json" ]; then
    log "📦 Проверка зависимостей Node.js..."
    
    # Проверяем наличие node_modules
    if [ ! -d "$HTML_DIR/node_modules" ]; then
        log "🔧 Установка npm зависимостей..."
        npm install --silent 2>&1 | tee -a "$LOG_FILE"
        
        # Проверяем установлен ли Vite
        if ! npm list vite 2>/dev/null | grep -q vite; then
            log "🔧 Vite не найден, устанавливаем отдельно..."
            npm install vite --save-dev --silent 2>&1 | tee -a "$LOG_FILE"
        fi
        
        log "✅ NPM зависимости установлены"
    else
        log "✅ node_modules уже существуют"
        
        # Проверяем наличие Vite даже если node_modules есть
        if ! npm list vite 2>/dev/null | grep -q vite; then
            log "🔧 Vite не найден в node_modules, устанавливаем..."
            npm install vite --save-dev --silent 2>&1 | tee -a "$LOG_FILE"
        fi
    fi
    
    # Проверяем наличие директории public/build и manifest.json
    if [ ! -f "$HTML_DIR/public/build/manifest.json" ]; then
        log "🔨 Сборка фронтенда..."
        
        # Пробуем разные команды сборки
        if grep -q '"build"' "$HTML_DIR/package.json"; then
            log "🔄 Запуск npm run build..."
            npm run build 2>&1 | tee -a "$LOG_FILE"
            BUILD_EXIT=$?
            if [ "$BUILD_EXIT" -eq 0 ]; then
                log "✅ Фронтенд успешно собран"
            else
                log "⚠️ npm run build завершился с ошибкой, проверяем установку Vite..."
                # Проверяем наличие vite в package.json
                if ! grep -q '"vite"' "$HTML_DIR/package.json" && ! grep -q 'laravel-vite-plugin' "$HTML_DIR/package.json"; then
                    log "🔧 Устанавливаем недостающие пакеты для Vite..."
                    npm install vite laravel-vite-plugin @vitejs/plugin-vue --save-dev --silent 2>&1 | tee -a "$LOG_FILE"
                fi
                log "🔄 Повторная попытка сборки..."
                npm run build 2>&1 | tee -a "$LOG_FILE"
            fi
        elif grep -q '"vite:build"' "$HTML_DIR/package.json"; then
            log "🔄 Запуск npm run vite:build..."
            npm run vite:build 2>&1 | tee -a "$LOG_FILE"
        elif grep -q '"production"' "$HTML_DIR/package.json"; then
            log "🔄 Запуск npm run production..."
            npm run production 2>&1 | tee -a "$LOG_FILE"
        else
            log "⚠️ Скрипт сборки не найден в package.json"
            log "🔧 Проверяем наличие vite в node_modules..."
            if [ -f "$HTML_DIR/node_modules/.bin/vite" ]; then
                log "🔄 Запускаем vite build напрямую..."
                npx vite build 2>&1 | tee -a "$LOG_FILE"
            fi
        fi
    else
        log "✅ Manifest.json уже существует"
    fi
    
    # Проверяем результат сборки
    if [ -f "$HTML_DIR/public/build/manifest.json" ]; then
        MANIFEST_SIZE=$(ls -la "$HTML_DIR/public/build/manifest.json" | awk '{print $5}')
        log "✅ Vite manifest найден: $MANIFEST_SIZE байт"
        
        # Проверяем содержимое manifest.json
        if [ "$MANIFEST_SIZE" -lt 100 ]; then
            log "⚠️ Manifest.json слишком маленький, возможно ошибка сборки"
            log "🔍 Содержимое manifest.json:"
            cat "$HTML_DIR/public/build/manifest.json" | tee -a "$LOG_FILE"
        fi
        
        # Устанавливаем права на собранные файлы
        chown -R www-data:www-data "$HTML_DIR/public/build" 2>/dev/null || true
        chmod -R 755 "$HTML_DIR/public/build" 2>/dev/null || true
        
        # Создаем симлинк если нужно (для Vite dev)
        if [ ! -L "$HTML_DIR/public/hot" ] && [ ! -e "$HTML_DIR/public/hot" ]; then
            ln -sf /var/www/html/storage/framework/hot "$HTML_DIR/public/hot" 2>/dev/null || true
        fi
    else
        log "⚠️ Vite manifest НЕ найден после сборки"
        log "🔧 Создаем правильную заглушку manifest.json..."
        cat > "$HTML_DIR/public/build/manifest.json" << 'EOF'
{
  "resources/js/app.js": {
    "file": "assets/app.js",
    "src": "resources/js/app.js",
    "isEntry": true
  },
  "resources/css/app.css": {
    "file": "assets/app.css",
    "src": "resources/css/app.css",
    "isEntry": true
  },
  "resources/sass/app.scss": {
    "file": "assets/app.scss",
    "src": "resources/sass/app.scss",
    "isEntry": true
  }
}
EOF
        chown www-data:www-data "$HTML_DIR/public/build/manifest.json" 2>/dev/null || true
        chmod 644 "$HTML_DIR/public/build/manifest.json" 2>/dev/null || true
        log "✅ Заглушка manifest.json создана с правильными путями"
    fi
else
    log "ℹ️ package.json не найден, пропускаем сборку фронтенда"
fi

# -----------------------
# Оптимизация и кэширование Laravel
# -----------------------
if [ -f "$HTML_DIR/artisan" ]; then
    log "⚡ Оптимизация Laravel..."
    
    # Очистка кэша (пропускаем если есть ошибки VK API)
    log "🧹 Очистка кэша..."
    php artisan cache:clear 2>&1 | tee -a "$LOG_FILE" || log "⚠️ Ошибка очистки кэша (игнорируем)"
    
    # Кэширование конфигурации (пропускаем если есть ошибки VK API)
    log "⚙️ Кэширование конфигурации..."
    php artisan config:cache 2>&1 | tee -a "$LOG_FILE" || log "⚠️ Ошибка кэширования конфигурации (игнорируем)"
    
    # Кэширование роутов (только для production)
    APP_ENV_VALUE="${APP_ENV:-local}"
    if [ "$APP_ENV_VALUE" = "production" ] || [ "$APP_ENV_VALUE" = "staging" ]; then
        log "🛣 Кэширование маршрутов..."
        php artisan route:cache 2>&1 | tee -a "$LOG_FILE" || log "⚠️ Ошибка кэширования маршрутов (игнорируем)"
    else
        log "ℹ️ Пропускаем кэширование маршрутов (режим: $APP_ENV_VALUE)"
    fi
    
    # Кэширование вьюх (пропускаем если есть ошибки VK API)
    log "👁 Кэширование шаблонов..."
    php artisan view:cache 2>&1 | tee -a "$LOG_FILE" || log "⚠️ Ошибка кэширования шаблонов (игнорируем)"
    
    # Оптимизация загрузчика
    log "📦 Оптимизация автозагрузчика..."
    composer dump-autoload --optimize 2>&1 | tee -a "$LOG_FILE"
    
    # Генерация ключей Vite
    log "🔑 Генерация ключей Vite..."
    php artisan vite:generate-key 2>&1 | tee -a "$LOG_FILE" || true
    
    log "✅ Оптимизация завершена"
fi

# -----------------------
# Финальные проверки
# -----------------------
log "🔍 Финальные проверки..."

# Проверяем что artisan доступен
if [ -f "$HTML_DIR/artisan" ]; then
    log "✅ artisan доступен"
    
    # Проверяем базовую команду
    if php artisan --version >/dev/null 2>&1; then
        ARTISAN_VERSION=$(php artisan --version 2>/dev/null | head -1)
        log "✅ Laravel: $ARTISAN_VERSION"
    else
        log "⚠️ Не удалось выполнить artisan --version"
    fi
else
    log "❌ artisan НЕ НАЙДЕН! Проблема с установкой проекта"
fi

# Проверяем наличие manifest.json
if [ -f "$HTML_DIR/public/build/manifest.json" ]; then
    log "✅ Vite manifest.json найден"
    # Проверяем содержит ли он нужные файлы
    if grep -q "resources/sass/app.scss" "$HTML_DIR/public/build/manifest.json"; then
        log "✅ Manifest содержит resources/sass/app.scss"
    else
        log "⚠️ Manifest НЕ содержит resources/sass/app.scss"
    fi
    if grep -q "resources/css/app.css" "$HTML_DIR/public/build/manifest.json"; then
        log "✅ Manifest содержит resources/css/app.css"
    else
        log "⚠️ Manifest НЕ содержит resources/css/app.css"
    fi
else
    log "⚠️ Vite manifest.json НЕ найден - возможны ошибки фронтенда"
fi

# Проверяем PHP-FPM конфигурацию
log "🔧 Проверка PHP-FPM конфигурации..."
if php-fpm -t >/dev/null 2>&1; then
    log "✅ PHP-FPM конфигурация корректна"
else
    log "❌ Ошибка в конфигурации PHP-FPM"
    php-fpm -t 2>&1 | tee -a "$LOG_FILE"
fi

# -----------------------
# Экспорт переменных из .env
# -----------------------
if [ -f "$ENV_FILE" ]; then
    log "📝 Экспорт переменных окружения из .env..."
    
    # Безопасный экспорт
    while IFS='=' read -r key value || [ -n "$key" ]; do
        # Пропускаем комментарии и пустые строки
        if echo "$key" | grep -q '^[[:space:]]*#' || [ -z "$key" ] || echo "$key" | grep -q '^[[:space:]]*$'; then
            continue
        fi
        
        # Убираем кавычки если есть
        value="${value%\"}"
        value="${value#\"}"
        value="${value%\'}"
        value="${value#\'}"
        
        # Убираем пробелы в начале ключа
        key=$(echo "$key" | sed 's/^[[:space:]]*//')
        
        # Экспортируем (кроме пустых значений)
        if [ -n "$value" ]; then
            export "$key"="$value"
            
            # Логируем (без значений паролей и секретов)
            if echo "$key" | grep -q -E '(PASSWORD|SECRET|KEY|TOKEN)' && [ -n "$value" ]; then
                log "   $key=******"
            elif [ ${#value} -gt 50 ]; then
                value_preview=$(echo "$value" | cut -c 1-50)
                log "   $key=${value_preview}..."
            else
                log "   $key=$value"
            fi
        fi
    done < "$ENV_FILE"
    
    log "✅ Переменные окружения экспортированы"
fi

# -----------------------
# Финальный отчет
# -----------------------
log "================================================"
log "🎉 ИНИЦИАЛИЗАЦИЯ ЗАВЕРШЕНА"
log "📊 Режим: $MODE"
log "📂 Директория: $HTML_DIR"
log "👤 Владелец файлов: $(stat -c "%U:%G" "$HTML_DIR" 2>/dev/null || echo "неизвестно")"
log "🔧 PHP-FPM готов к работе"
log "================================================"

log "🚀 Запуск PHP-FPM..."
exec php-fpm