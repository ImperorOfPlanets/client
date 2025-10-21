#!/bin/sh
set -e

STATUS_FILE="/var/log/vpn-status.txt"
LOG_FILE="/logs/healthcheck.log"

mkdir -p /var/log
chmod 777 /var/log

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

log "=== Healthcheck started ==="

# Берём первый TUN интерфейс
TUN_IF=$(ip -o link show type tun | head -1 | awk -F': ' '{print $2}')

if [ -z "$TUN_IF" ]; then
    echo "FAIL:NO_TUN" > "$STATUS_FILE"
    log "Нет TUN интерфейса"
    exit 1
fi

VPN_IP=$(ip -4 addr show "$TUN_IF" 2>/dev/null | grep -oE 'inet [0-9.]+' | awk '{print $2}')

if [ -z "$VPN_IP" ]; then
    echo "FAIL:NO_IP" > "$STATUS_FILE"
    log "IP не найден на $TUN_IF"
    exit 1
fi

echo "OK:$VPN_IP" > "$STATUS_FILE"
log "VPN интерфейс поднят, IP=$VPN_IP"
log "=== Healthcheck completed ==="

exit 0
