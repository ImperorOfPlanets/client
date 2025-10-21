#!/bin/sh
set -e

LOG_DIR="/var/log/nginx"
mkdir -p "$LOG_DIR"
ENTRYPOINT_LOG="$LOG_DIR/entrypoint.log"

entrypoint_log() {
    local message="$@"
    [ -z "${NGINX_ENTRYPOINT_QUIET_LOGS:-}" ] && echo "$message"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $message" >> "$ENTRYPOINT_LOG"
}

entrypoint_log "=== Starting Nginx Entrypoint ==="

# Значения по умолчанию
export MAX_BODY_SIZE=${MAX_BODY_SIZE:-8M}
export NGINX_DOMAIN=${NGINX_DOMAIN:-localhost}
export PHP_CONTAINER=${PHP_CONTAINER:-php-${PROJECTNAME}}
export REVERB_CONTAINER=${REVERB_CONTAINER:-reverb-${PROJECTNAME}}

# WEBSSH
export WEBSSH_CONTAINER=${WEBSSH_CONTAINER:-webssh-${PROJECTNAME}}
export WEBSSH_DOMAIN=${WEBSSH_DOMAIN:-ssh.${NGINX_DOMAIN}}

# N8N
export N8N_CONTAINER=${N8N_CONTAINER:-n8n-${PROJECTNAME}}

entrypoint_log "MAX_BODY_SIZE set to: $MAX_BODY_SIZE"
entrypoint_log "NGINX_DOMAIN set to: $NGINX_DOMAIN"
entrypoint_log "PHP_CONTAINER set to: $PHP_CONTAINER"
entrypoint_log "REVERB_CONTAINER set to: $REVERB_CONTAINER"

# ИСПРАВЛЕННЫЕ ЛОГИ - были неправильные переменные
entrypoint_log "WEBSSH_CONTAINER set to: $WEBSSH_CONTAINER"
entrypoint_log "WEBSSH_DOMAIN set to: $WEBSSH_DOMAIN"

# ================================
# Запуск всех скриптов из /docker-entrypoint.d/
# ================================
if [ "$1" = "nginx" -o "$1" = "nginx-debug" ]; then
    if find "/docker-entrypoint.d/" -mindepth 1 -print -quit 2>/dev/null | grep -q .; then
        entrypoint_log "Processing /docker-entrypoint.d/"
        find "/docker-entrypoint.d/" -follow -type f -print | sort -n | while read -r f; do
            case "$f" in
                *.sh)
                    if [ -x "$f" ]; then
                        entrypoint_log "Executing: $f"
                        "$f" >> "$ENTRYPOINT_LOG" 2>&1
                    fi
                    ;;
                *) entrypoint_log "Skipping: $f";;
            esac
        done
    fi
fi

# ================================
# Генерация конфига из шаблона
# ================================
entrypoint_log "Generating Nginx config..."
cat /etc/nginx/templates/default.conf.template | \
    sed 's|\${MAX_BODY_SIZE}|'"${MAX_BODY_SIZE}"'|g' | \
    sed 's|\${NGINX_DOMAIN}|'"${NGINX_DOMAIN}"'|g' | \
    sed 's|\${PHP_CONTAINER}|'"${PHP_CONTAINER}"'|g' | \
    sed 's|\${REVERB_CONTAINER}|'"${REVERB_CONTAINER}"'|g' | \
    sed 's|\${WEBSSH_CONTAINER}|'"${WEBSSH_CONTAINER}"'|g' | \
    sed 's|\${WEBSSH_DOMAIN}|'"${WEBSSH_DOMAIN}"'|g' | \
    sed 's|\${N8N_CONTAINER}|'"${N8N_CONTAINER}"'|g' \
    > /etc/nginx/conf.d/default.conf

# Выводим конфиг в лог и на экран
entrypoint_log "Generated Nginx configuration content:"
[ -z "${NGINX_ENTRYPOINT_QUIET_LOGS:-}" ] && cat /etc/nginx/conf.d/default.conf
cat /etc/nginx/conf.d/default.conf >> "$ENTRYPOINT_LOG"

# Проверка конфига
entrypoint_log "Generated config validation:"
nginx -t >> "$ENTRYPOINT_LOG" 2>&1

# ================================
# Вывод доступных URL после запуска
# ================================
entrypoint_log "Available Nginx addresses:"
for ip in $(hostname -i); do
    entrypoint_log "HTTP  -> http://$ip"
    entrypoint_log "HTTPS -> https://$ip"
done

entrypoint_log "=== Entrypoint completed, launching Nginx ==="
exec "$@"
