#!/bin/sh
set -e

LOG_FILE="/var/log/vpn/vpn_manager.log"
CONFIG_FILE="/vpn/config.ovpn"
AUTH_FILE="/vpn/auth.txt"

# Создаем все необходимые директории
mkdir -p /vpn /logs /var/vpn
chmod 777 /vpn /logs /var/vpn

# Создаем и настраиваем лог-файл
touch "$LOG_FILE"
chmod 666 "$LOG_FILE"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] [start] $1" | tee -a "$LOG_FILE"
}

log "=== Запуск VPN контейнера ==="
log "ENABLED_SERVICES: ${ENABLED_SERVICES:-none}"


# -------------------------------
# 1️⃣ Загрузка .ovpn файла
# -------------------------------
if [ ! -f "$CONFIG_FILE" ]; then
    log "Загрузка .ovpn файла..."
    curl -fsSL -o "$CONFIG_FILE" "https://myidon.site/install/public.ovpn" || log "Не удалось скачать .ovpn"
    log ".ovpn файл успешно загружен"
fi

# -------------------------------
# 2️⃣ Файл авторизации
# -------------------------------
if [ -n "$VPN_USERNAME" ] && [ -n "$VPN_PASSWORD" ]; then
    echo "$VPN_USERNAME" > "$AUTH_FILE"
    echo "$VPN_PASSWORD" >> "$AUTH_FILE"
    chmod 600 "$AUTH_FILE"
    log "Файл авторизации создан: $AUTH_FILE"
fi

# -------------------------------
# 3️⃣ Запуск OpenVPN
# -------------------------------
OPENVPN_ARGS="--config $CONFIG_FILE --auth-nocache --verb 3"
[ -f "$AUTH_FILE" ] && OPENVPN_ARGS="$OPENVPN_ARGS --auth-user-pass $AUTH_FILE"

openvpn $OPENVPN_ARGS &
OPENVPN_PID=$!

# -------------------------------
# 4️⃣ Ожидание TUN
# -------------------------------
MAX_WAIT=30
WAIT_INTERVAL=2
TUN_IF=""
i=0
log "Ожидание TUN..."
while [ $i -lt $(expr $MAX_WAIT / $WAIT_INTERVAL) ]; do
    TUN_IF=$(ip -o link show type tun | head -1 | awk -F': ' '{print $2}')
    [ -n "$TUN_IF" ] && break
    sleep $WAIT_INTERVAL
    i=$(expr $i + 1)
done
[ -z "$TUN_IF" ] && log "TUN не найден, продолжаем работать..." || log "TUN найден: $TUN_IF"

# -------------------------------
# 5️⃣ Определение IP VPN
# -------------------------------
VPN_CLIENT_IP=""
for _ in $(seq 1 $(expr $MAX_WAIT / $WAIT_INTERVAL)); do
    VPN_CLIENT_IP=$(ip -4 addr show $TUN_IF 2>/dev/null | grep -oE 'inet [0-9.]+' | awk '{print $2}')
    [ -n "$VPN_CLIENT_IP" ] && break
    sleep $WAIT_INTERVAL
done
log "VPN IP: ${VPN_CLIENT_IP:-не определён}"

# -------------------------------
# 6️⃣ Определение IP сервисов на основе ENABLED_SERVICES
# -------------------------------

# Функция для проверки включен ли сервис
is_service_enabled() {
    echo "${ENABLED_SERVICES:-}" | grep -qi "$1"
}

# Функция для получения IP контейнера
get_container_ip() {
    local container_name=$1
    local ip=""
    local i=0
    while [ -z "$ip" ] && [ $i -lt 5 ]; do
        ip=$(getent hosts "$container_name" | awk '{print $1}')
        [ -n "$ip" ] && break
        sleep 2
        i=$(expr $i + 1)
    done
    echo "$ip"
}

# Определяем IP для каждого сервиса если он включен
NGINX_IP=""
if is_service_enabled "nginx"; then
    NGINX_IP=$(get_container_ip "nginx-${PROJECTNAME}")
    log "Nginx IP: ${NGINX_IP:-не доступен}"
else
    log "Nginx отключен в ENABLED_SERVICES"
fi

RUSTDESK_HBBS_IP=""
RUSTDESK_HBBR_IP=""
if is_service_enabled "rustdesk"; then
    RUSTDESK_HBBS_IP=$(get_container_ip "hbbs-${PROJECTNAME}")
    RUSTDESK_HBBR_IP=$(get_container_ip "hbbr-${PROJECTNAME}")
    log "RustDesk HBBS IP: ${RUSTDESK_HBBS_IP:-не доступен}"
    log "RustDesk HBBR IP: ${RUSTDESK_HBBR_IP:-не доступен}"
else
    log "RustDesk отключен в ENABLED_SERVICES"
fi

# Добавьте здесь другие сервисы по аналогии
# Например:
# MARIADB_IP=""
# if is_service_enabled "mariadb"; then
#     MARIADB_IP=$(get_container_ip "mariadb-${PROJECTNAME}")
#     log "MariaDB IP: ${MARIADB_IP:-не доступен}"
# fi

# -------------------------------
# 7️⃣ Настройка iptables
# -------------------------------
iptables -t nat -F
iptables -t nat -N VPN_PROXY 2>/dev/null || true
iptables -t nat -F VPN_PROXY

# Правила для Nginx
if [ -n "$NGINX_IP" ]; then
    iptables -t nat -A PREROUTING -p tcp --dport 80 -j DNAT --to-destination "$NGINX_IP:80"
    iptables -t nat -A PREROUTING -p tcp --dport 443 -j DNAT --to-destination "$NGINX_IP:443"
    iptables -t nat -A POSTROUTING -s 10.8.0.0/24 -d "$NGINX_IP" -j MASQUERADE
    iptables -t nat -A POSTROUTING -s 10.8.1.0/24 -d "$NGINX_IP" -j MASQUERADE
    iptables -A FORWARD -s 10.8.0.0/24 -d "$NGINX_IP" -p tcp --dport 80 -j ACCEPT
    iptables -A FORWARD -s 10.8.0.0/24 -d "$NGINX_IP" -p tcp --dport 443 -j ACCEPT
    iptables -A FORWARD -s 10.8.1.0/24 -d "$NGINX_IP" -p tcp --dport 80 -j ACCEPT
    iptables -A FORWARD -s 10.8.1.0/24 -d "$NGINX_IP" -p tcp --dport 443 -j ACCEPT
    log "Проброс TCP -> Nginx настроен"
fi

# Правила для RustDesk
if [ -n "$RUSTDESK_HBBS_IP" ] && [ -n "$RUSTDESK_HBBR_IP" ]; then
    # Проброс портов для RustDesk - ИСПРАВЛЕНО!
    iptables -t nat -A PREROUTING -p tcp --dport 21115 -j DNAT --to-destination "$RUSTDESK_HBBS_IP:21115"
    iptables -t nat -A PREROUTING -p tcp --dport 21116 -j DNAT --to-destination "$RUSTDESK_HBBS_IP:21116"
    iptables -t nat -A PREROUTING -p udp --dport 21116 -j DNAT --to-destination "$RUSTDESK_HBBS_IP:21116"
    iptables -t nat -A PREROUTING -p tcp --dport 21117 -j DNAT --to-destination "$RUSTDESK_HBBR_IP:21117"
    iptables -t nat -A PREROUTING -p tcp --dport 21118 -j DNAT --to-destination "$RUSTDESK_HBBS_IP:21118"
    iptables -t nat -A PREROUTING -p tcp --dport 21119 -j DNAT --to-destination "$RUSTDESK_HBBS_IP:21119"
    
    # Маскировка для RustDesk
    iptables -t nat -A POSTROUTING -s 10.8.0.0/24 -d "$RUSTDESK_HBBS_IP" -j MASQUERADE
    iptables -t nat -A POSTROUTING -s 10.8.0.0/24 -d "$RUSTDESK_HBBR_IP" -j MASQUERADE
    iptables -t nat -A POSTROUTING -s 10.8.1.0/24 -d "$RUSTDESK_HBBS_IP" -j MASQUERADE
    iptables -t nat -A POSTROUTING -s 10.8.1.0/24 -d "$RUSTDESK_HBBR_IP" -j MASQUERADE
    
    # Разрешение форвардинга для RustDesk - ИСПРАВЛЕНО!
    iptables -A FORWARD -s 10.8.0.0/24 -d "$RUSTDESK_HBBS_IP" -p tcp --dport 21115 -j ACCEPT
    iptables -A FORWARD -s 10.8.0.0/24 -d "$RUSTDESK_HBBS_IP" -p tcp --dport 21116 -j ACCEPT
    iptables -A FORWARD -s 10.8.0.0/24 -d "$RUSTDESK_HBBS_IP" -p udp --dport 21116 -j ACCEPT
    iptables -A FORWARD -s 10.8.0.0/24 -d "$RUSTDESK_HBBS_IP" -p tcp --dport 21118 -j ACCEPT
    iptables -A FORWARD -s 10.8.0.0/24 -d "$RUSTDESK_HBBS_IP" -p tcp --dport 21119 -j ACCEPT
    iptables -A FORWARD -s 10.8.0.0/24 -d "$RUSTDESK_HBBR_IP" -p tcp --dport 21117 -j ACCEPT
    
    iptables -A FORWARD -s 10.8.1.0/24 -d "$RUSTDESK_HBBS_IP" -p tcp --dport 21115 -j ACCEPT
    iptables -A FORWARD -s 10.8.1.0/24 -d "$RUSTDESK_HBBS_IP" -p tcp --dport 21116 -j ACCEPT
    iptables -A FORWARD -s 10.8.1.0/24 -d "$RUSTDESK_HBBS_IP" -p udp --dport 21116 -j ACCEPT
    iptables -A FORWARD -s 10.8.1.0/24 -d "$RUSTDESK_HBBS_IP" -p tcp --dport 21118 -j ACCEPT
    iptables -A FORWARD -s 10.8.1.0/24 -d "$RUSTDESK_HBBS_IP" -p tcp --dport 21119 -j ACCEPT
    iptables -A FORWARD -s 10.8.1.0/24 -d "$RUSTDESK_HBBR_IP" -p tcp --dport 21117 -j ACCEPT
    
    log "Проброс портов RustDesk настроен"
fi

# Общее правило для установленных соединений
iptables -A FORWARD -m state --state ESTABLISHED,RELATED -j ACCEPT

# -------------------------------
# 8️⃣ Обновление /etc/hosts
# -------------------------------
if [ -n "$NGINX_DOMAIN" ] && [ -n "$NGINX_IP" ]; then
    TMP=$(mktemp)
    grep -v "$NGINX_DOMAIN" /etc/hosts > "$TMP" || true
    echo "$NGINX_IP $NGINX_DOMAIN" >> "$TMP"
    cat "$TMP" > /etc/hosts || log "Не удалось обновить /etc/hosts"
    rm -f "$TMP"
    log "/etc/hosts обновлён: $NGINX_DOMAIN -> $NGINX_IP"
fi

# -------------------------------
# 8️⃣.1 Обновление /etc/hosts для WebSSH домена
# -------------------------------
if [ -n "$WEBSSH_DOMAIN" ] && [ -n "$NGINX_IP" ]; then
    TMP=$(mktemp)
    grep -v "$WEBSSH_DOMAIN" /etc/hosts > "$TMP" || true
    echo "$NGINX_IP $WEBSSH_DOMAIN" >> "$TMP"
    cat "$TMP" > /etc/hosts || log "Не удалось обновить /etc/hosts для WebSSH"
    rm -f "$TMP"
    log "/etc/hosts обновлён: $WEBSSH_DOMAIN -> $NGINX_IP"
fi

# -------------------------------
# 9️⃣ Продолжаем работу без завершения
# -------------------------------
log "VPN запущен, контейнер остаётся активным"
wait $OPENVPN_PID