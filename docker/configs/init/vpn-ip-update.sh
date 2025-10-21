#!/bin/sh
set -e

PROJECTNAME=${PROJECTNAME}
CORE_URL="https://core.myidon.site/dns/update"
LOG_FILE="/var/log/vpn/vpn_manager.log"

mkdir -p $(dirname "$LOG_FILE")
touch "$LOG_FILE"

log() {
    echo "[$(date '+%Y-%m-%dT%H:%M:%S')] [update] $1" | tee -a "$LOG_FILE"
}

log "=== VPN IP Update Started ==="

# -------------------------------
# 1️⃣ Получаем последний VPN IP из [start] записей
# -------------------------------
LAST_START_LINE=$(grep "\[start\] VPN IP:" "$LOG_FILE" | tail -n1)

if [ -z "$LAST_START_LINE" ]; then
    log "Нет записей VPN IP в логе"
    exit 0
fi

# Извлекаем IP
VPN_IP=$(echo "$LAST_START_LINE" | sed -n 's/.*VPN IP: \([^ ]*\).*/\1/p')

# Время извлечения
EXTRACT_TIME=$(date '+%Y-%m-%dT%H:%M:%S')

log "Извлечённый VPN IP: $VPN_IP"
log "Время извлечения: $EXTRACT_TIME"

# -------------------------------
# 2️⃣ Проверяем доступность CORE
# -------------------------------
MAX_RETRIES=3
RETRY_DELAY=60  # в секундах

for attempt in $(seq 1 $MAX_RETRIES); do
    if curl -k -s -f --connect-timeout 10 --max-time 30 "$CORE_URL" >/dev/null 2>&1; then
        log "CORE доступен"
        break
    else
        log "CORE недоступен, попытка $attempt из $MAX_RETRIES"
        if [ "$attempt" -lt "$MAX_RETRIES" ]; then
            sleep $RETRY_DELAY
        else
            log "CORE недоступен, пропускаем отправку"
            exit 1
        fi
    fi
done


# -------------------------------
# 3️⃣ Сравниваем с предыдущим отправленным IP
# -------------------------------
LAST_SENT_IP=$(grep "\[update\] IP успешно отправлен" "$LOG_FILE" | tail -n1 | awk '{print $NF}' || echo "")
if [ "$LAST_SENT_IP" = "$VPN_IP" ]; then
    log "IP не изменился ($VPN_IP), отправка не требуется"
else
    log "IP изменился: $LAST_SENT_IP -> $VPN_IP, отправляем на CORE"

    for attempt in 1 2 3; do
        HTTP_STATUS=$(curl -k -s -o /dev/null -w "%{http_code}" -X POST "$CORE_URL" \
            -H "Content-Type: application/x-www-form-urlencoded" \
            -d "ip=$VPN_IP&PROJECTNAME=$PROJECTNAME" \
            --connect-timeout 10 --max-time 30 || echo "000")
        if [ "$HTTP_STATUS" -ge 200 ] && [ "$HTTP_STATUS" -lt 300 ]; then
            log "IP успешно отправлен на CORE $VPN_IP (HTTP $HTTP_STATUS)"
            break
        else
            log "Ошибка отправки IP (HTTP $HTTP_STATUS), попытка $attempt"
        fi
    done
fi
