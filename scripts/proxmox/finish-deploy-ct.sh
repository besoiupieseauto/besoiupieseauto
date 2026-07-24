#!/usr/bin/env bash
# Continuă deploy după git clone (SKIP_DB / crash la uploads.zip).
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/besoiupieseauto.ro}"
MIGRATE_DIR="${MIGRATE_DIR:-/root/migrate}"
APP_URL="${APP_URL:-http://192.168.1.50}"
DB_USER="${DB_USER:-besoiu}"
DB_PASS="${DB_PASS:-}"
DB_MAIN="${DB_MAIN:-besoiupieseauto.ro}"
DB_LEGACY="${DB_LEGACY:-caietcom_comenzilv}"

if [[ -z "$DB_PASS" ]]; then
  DB_PASS="$(openssl rand -base64 18 | tr -d '/+=' | head -c 20)"
fi

log() { echo "[finish] $*"; }

[[ -d "$APP_DIR" ]] || { echo "missing $APP_DIR"; exit 1; }

[[ -f "$MIGRATE_DIR/app-Config.env" ]] && cp -f "$MIGRATE_DIR/app-Config.env" "$APP_DIR/app/Config/.env"
[[ -f "$MIGRATE_DIR/app-Backend.env" ]] && cp -f "$MIGRATE_DIR/app-Backend.env" "$APP_DIR/app/Backend/.env"
[[ -f "$MIGRATE_DIR/admin.env" ]] && cp -f "$MIGRATE_DIR/admin.env" "$APP_DIR/admin/.env"
if [[ -f "$MIGRATE_DIR/uploads.zip" ]]; then
  unzip -o -q "$MIGRATE_DIR/uploads.zip" -d "$APP_DIR/app/Storage/uploads/" || log "uploads.zip skip"
fi

patch_env() {
  local f="$1"
  [[ -f "$f" ]] || return 0
  [[ -f "${f}.bak-proxmox" ]] || cp -f "$f" "${f}.bak-proxmox"
  set_kv() {
    local key="$1" val="$2" file="$3"
    if grep -qE "^${key}=" "$file"; then
      sed -i "s|^${key}=.*|${key}=${val}|" "$file"
    else
      printf '\n%s=%s\n' "$key" "$val" >> "$file"
    fi
  }
  set_kv "APP_ENV" "production" "$f"
  set_kv "APP_URL" "$APP_URL" "$f"
  set_kv "DB_HOST" "127.0.0.1" "$f"
  set_kv "DB_USER" "$DB_USER" "$f"
  set_kv "DB_PASS" "$DB_PASS" "$f"
  set_kv "DB_NAME" "$DB_MAIN" "$f"
  set_kv "LEGACY_DB_HOST" "127.0.0.1" "$f"
  set_kv "LEGACY_DB_USER" "$DB_USER" "$f"
  set_kv "LEGACY_DB_PASS" "$DB_PASS" "$f"
  set_kv "LEGACY_DB_NAME" "$DB_LEGACY" "$f"
  set_kv "STEALTH_BROWSER_ENABLED" "0" "$f"
  set_kv "OLLAMA_ENABLED" "0" "$f"
  set_kv "API_AUTOMATION_DISABLED" "1" "$f"
  set_kv "AI_AGENT_CYCLE_ENABLED" "0" "$f"
  set_kv "ASYNC_FILE_FALLBACK" "1" "$f"
}

patch_env "$APP_DIR/app/Config/.env"
patch_env "$APP_DIR/app/Backend/.env"
patch_env "$APP_DIR/admin/.env"

mkdir -p \
  "$APP_DIR/app/Storage/cache" \
  "$APP_DIR/app/Storage/uploads" \
  "$APP_DIR/app/Storage/seo" \
  "$APP_DIR/app/Backend/storage/cache" \
  "$APP_DIR/app/Backend/storage/logs" \
  "$APP_DIR/app/Backend/storage/queue" \
  "$APP_DIR/app/Backend/storage/async_jobs" \
  "$APP_DIR/app/Backend/storage/rate_limits" \
  "$APP_DIR/app/Backend/storage/imports" \
  "$APP_DIR/admin/storage/cache" \
  "$APP_DIR/admin/storage/logs"

systemctl enable --now mariadb >/dev/null 2>&1 || true
mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_MAIN}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_LEGACY}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';" || true
mysql -e "GRANT ALL PRIVILEGES ON \`${DB_MAIN}\`.* TO '${DB_USER}'@'localhost';"
mysql -e "GRANT ALL PRIVILEGES ON \`${DB_LEGACY}\`.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"

if [[ -f "$APP_DIR/scripts/proxmox/apache-besoiu.conf" ]]; then
  cp -f "$APP_DIR/scripts/proxmox/apache-besoiu.conf" /etc/apache2/sites-available/besoiu.conf
elif [[ -f "$MIGRATE_DIR/apache-besoiu.conf" ]]; then
  cp -f "$MIGRATE_DIR/apache-besoiu.conf" /etc/apache2/sites-available/besoiu.conf
fi

a2enmod rewrite headers deflate expires >/dev/null
a2dissite 000-default.conf >/dev/null 2>&1 || true
a2ensite besoiu.conf >/dev/null
chown -R www-data:www-data \
  "$APP_DIR/app/Storage" \
  "$APP_DIR/app/Backend/storage" \
  "$APP_DIR/admin/storage" || true
apache2ctl configtest
systemctl reload apache2

umask 077
cat > /root/besoiu-db-credentials.txt <<CREDS
DB_HOST=127.0.0.1
DB_USER=$DB_USER
DB_PASS=$DB_PASS
DB_NAME=$DB_MAIN
LEGACY_DB_NAME=$DB_LEGACY
APP_URL=$APP_URL
CREDS
chmod 600 /root/besoiu-db-credentials.txt

log "Gata (fără import SQL încă)."
log "Magazin: $APP_URL/"
cat /root/besoiu-db-credentials.txt
