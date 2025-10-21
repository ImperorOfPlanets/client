#!/bin/sh
set -e

PROJECTNAME=${PROJECTNAME}
CORE_URL="https://core.myidon.site/dns/update"
LOG_FILE="/logs/vpn_manager.log"
LAST_IP_FILE="/tmp/vpn_last_ip.txt"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# -------------------------------
# 1️⃣ Проверяем доступность CORE
# -------------------------------
if ! curl -k -s -f --connect-timeout 10 --max-time 30 "$CORE_URL" >/dev/null 2>&1; then
    log "CORE недоступен, пропускаем отправку"
    exit 0
fi
log "CORE доступен"

# -------------------------------
# 2️⃣ Проверяем лог VPN
# -------------------------------
if [ ! -f "$LOG_FILE" ]; then
    log "Лог VPN не найден: $LOG_FILE"
    exit 0
fi

LAST_3_LINES=$(grep "VPN IP:" "$LOG_FILE" | tail -n 3)
if [ -z "$LAST_3_LINES" ]; then
    log "Нет записей VPN IP в логе"
    exit 0
fi

VPN_IP=$(echo "$LAST_3_LINES" | tail -n1 | awk '{print $NF}')
if [ -z "$VPN_IP" ]; then
    log "Не удалось извлечь последний IP"
    exit 0
fi

# -------------------------------
# 3️⃣ Сравниваем с последним IP
# -------------------------------
LAST_IP=$(cat "$LAST_IP_FILE" 2>/dev/null || echo "")
if [ "$LAST_IP" = "$VPN_IP" ]; then
    log "IP не изменился: $VPN_IP"
    exit 0
fi

echo "$VPN_IP" > "$LAST_IP_FILE"
log "Отправка IP на CORE: $VPN_IP, PROJECTNAME: $PROJECTNAME"

for attempt in 1 2 3; do
    HTTP_STATUS=$(curl -k -s -o /dev/null -w "%{http_code}" -X POST "$CORE_URL" \
        -H "Content-Type: application/x-www-form-urlencoded" \
        -d "ip=$VPN_IP&PROJECTNAME=$PROJECTNAME" \
        --connect-timeout 10 --max-time 30 || echo "000")
    if [ "$HTTP_STATUS" -ge 200 ] && [ "$HTTP_STATUS" -lt 300 ]; then
        log "IP успешно отправлен (HTTP $HTTP_STATUS)"
        break
    else
        log "Ошибка отправки IP (HTTP $HTTP_STATUS), попытка $attempt"
    fi
done

# -------------------------------
# 4️⃣ Очищаем лог, оставляя только последние 3 записи VPN
# -------------------------------
grep "VPN IP:" "$LOG_FILE" | tail -n 3 > "${LOG_FILE}.tmp" && mv "${LOG_FILE}.tmp" "$LOG_FILE"
log "Лог VPN очищен, оставлены последние 3 записи"
