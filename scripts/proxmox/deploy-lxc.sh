#!/usr/bin/env bash
# Deploy Besoiu pe LXC Debian (Apache + PHP + MariaDB).
# Rulează ca root în container, după ce ai:
#   - USB/pachet în MIGRATE_DIR (implicit /root/migrate)
#   - sau cod deja clonat în APP_DIR
#
# Exemplu:
#   bash scripts/proxmox/deploy-lxc.sh
#   MIGRATE_DIR=/root/migrate APP_URL=http://192.168.1.50 bash scripts/proxmox/deploy-lxc.sh

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/besoiupieseauto.ro}"
MIGRATE_DIR="${MIGRATE_DIR:-/root/migrate}"
GIT_REPO="${GIT_REPO:-https://github.com/besoiupieseauto/besoiupieseauto.git}"
GIT_BRANCH="${GIT_BRANCH:-upload/snapshot-20260723}"
APP_URL="${APP_URL:-http://192.168.1.50}"
DB_USER="${DB_USER:-besoiu}"
DB_PASS="${DB_PASS:-}"
DB_MAIN="${DB_MAIN:-besoiupieseauto.ro}"
DB_LEGACY="${DB_LEGACY:-caietcom_comenzilv}"
SKIP_GIT="${SKIP_GIT:-0}"
SKIP_COMPOSER="${SKIP_COMPOSER:-0}"
SKIP_DB="${SKIP_DB:-0}"

log() { echo "[deploy] $*"; }
die() { echo "[deploy][ERR] $*" >&2; exit 1; }

[[ "$(id -u)" -eq 0 ]] || die "Rulează ca root."

if [[ -z "$DB_PASS" ]]; then
  DB_PASS="$(openssl rand -base64 18 | tr -d '/+=' | head -c 20)"
  log "DB_PASS generat automat (salvat la final în /root/besoiu-db-credentials.txt)"
fi

export DEBIAN_FRONTEND=noninteractive

# --- PHP: preferă 8.3 (Sury), altfel 8.2 din Debian ---
install_php_stack() {
  local php_ver=""
  if apt-cache show php8.3-cli >/dev/null 2>&1; then
    php_ver="8.3"
  elif apt-cache show php8.2-cli >/dev/null 2>&1; then
    php_ver="8.2"
  fi

  if [[ -z "$php_ver" ]]; then
    log "Adaug repo Sury pentru PHP 8.3..."
    apt-get install -y ca-certificates curl gnupg lsb-release apt-transport-https
    curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /usr/share/keyrings/deb.sury.org-php.gpg
    echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
      > /etc/apt/sources.list.d/php-sury.list
    apt-get update
    php_ver="8.3"
  fi

  log "Instalez PHP ${php_ver} + Apache + MariaDB..."
  apt-get install -y \
    apache2 mariadb-server git unzip curl composer \
    "php${php_ver}" "php${php_ver}-cli" "libapache2-mod-php${php_ver}" \
    "php${php_ver}-mysql" "php${php_ver}-mbstring" "php${php_ver}-curl" \
    "php${php_ver}-zip" "php${php_ver}-gd" "php${php_ver}-intl" \
    "php${php_ver}-xml" "php${php_ver}-opcache"
}

log "apt update..."
apt-get update
install_php_stack

a2enmod rewrite headers deflate expires >/dev/null

# --- Cod via Git ---
if [[ "$SKIP_GIT" != "1" ]]; then
  mkdir -p "$(dirname "$APP_DIR")"
  if [[ -d "$APP_DIR/.git" ]]; then
    log "Repo există — fetch/checkout $GIT_BRANCH"
    git -C "$APP_DIR" fetch origin
    git -C "$APP_DIR" checkout "$GIT_BRANCH"
    git -C "$APP_DIR" pull --ff-only origin "$GIT_BRANCH" || true
  else
    if [[ -d "$APP_DIR" ]] && [[ -n "$(ls -A "$APP_DIR" 2>/dev/null || true)" ]]; then
      die "$APP_DIR există și nu e gol / fără .git. Setează SKIP_GIT=1 sau golește directorul."
    fi
    log "git clone -b $GIT_BRANCH"
    git clone -b "$GIT_BRANCH" "$GIT_REPO" "$APP_DIR"
  fi
else
  [[ -d "$APP_DIR" ]] || die "SKIP_GIT=1 dar lipsește $APP_DIR"
fi

cd "$APP_DIR"

# --- Composer ---
if [[ "$SKIP_COMPOSER" != "1" ]]; then
  if [[ -f "$APP_DIR/app/Backend/composer.json" ]]; then
    log "composer install (app/Backend)"
    (cd "$APP_DIR/app/Backend" && composer install --no-dev --optimize-autoloader --no-interaction)
  else
    log "Skip composer — lipsește app/Backend/composer.json"
  fi
fi

# --- Foldere runtime ---
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

# --- .env din pachet USB ---
if [[ -d "$MIGRATE_DIR" ]]; then
  log "Copiez .env din $MIGRATE_DIR"
  [[ -f "$MIGRATE_DIR/app-Config.env" ]] && cp -f "$MIGRATE_DIR/app-Config.env" "$APP_DIR/app/Config/.env"
  [[ -f "$MIGRATE_DIR/app-Backend.env" ]] && cp -f "$MIGRATE_DIR/app-Backend.env" "$APP_DIR/app/Backend/.env"
  [[ -f "$MIGRATE_DIR/admin.env" ]] && cp -f "$MIGRATE_DIR/admin.env" "$APP_DIR/admin/.env"
  [[ -f "$MIGRATE_DIR/robot.env" ]] && mkdir -p "$APP_DIR/robot" && cp -f "$MIGRATE_DIR/robot.env" "$APP_DIR/robot/.env"

  if [[ -f "$MIGRATE_DIR/uploads.zip" ]]; then
    log "Dezarhivez uploads.zip"
    # Compress-Archive pe Windows poate genera zip cu backslash — nu oprim deploy-ul
    unzip -o -q "$MIGRATE_DIR/uploads.zip" -d "$APP_DIR/app/Storage/uploads/" || log "uploads.zip: skip/parțial (OK dacă e gol)"
  fi
else
  log "ATENȚIE: $MIGRATE_DIR lipsește — configurează .env manual."
fi

# Patch minimal .env (APP_URL / DB / AI off)
patch_env_file() {
  local f="$1"
  [[ -f "$f" ]] || return 0
  # backup o singură dată
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

patch_env_file "$APP_DIR/app/Config/.env"
patch_env_file "$APP_DIR/app/Backend/.env"
patch_env_file "$APP_DIR/admin/.env"

# --- MariaDB ---
if [[ "$SKIP_DB" != "1" ]]; then
  systemctl enable --now mariadb >/dev/null 2>&1 || service mariadb start || true

  log "Creez baze + user $DB_USER"
  mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_MAIN}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_LEGACY}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
  mysql -e "GRANT ALL PRIVILEGES ON \`${DB_MAIN}\`.* TO '${DB_USER}'@'localhost';"
  mysql -e "GRANT ALL PRIVILEGES ON \`${DB_LEGACY}\`.* TO '${DB_USER}'@'localhost';"
  mysql -e "FLUSH PRIVILEGES;"

  if [[ -f "$MIGRATE_DIR/${DB_MAIN}.sql" ]]; then
    log "Import ${DB_MAIN}.sql (poate dura)..."
    mysql "$DB_MAIN" < "$MIGRATE_DIR/${DB_MAIN}.sql"
  else
    log "Lipsește dump ${DB_MAIN}.sql — rulează migrațiile din admin/migrations dacă e nevoie."
  fi

  if [[ -f "$MIGRATE_DIR/${DB_LEGACY}.sql" ]]; then
    log "Import ${DB_LEGACY}.sql..."
    mysql "$DB_LEGACY" < "$MIGRATE_DIR/${DB_LEGACY}.sql"
  else
    log "Lipsește dump ${DB_LEGACY}.sql (opțional)."
  fi
fi

# --- Apache vhost (din repo clonat sau din pachetul USB) ---
VHOST_DST="/etc/apache2/sites-available/besoiu.conf"
if [[ -f "$APP_DIR/scripts/proxmox/apache-besoiu.conf" ]]; then
  cp -f "$APP_DIR/scripts/proxmox/apache-besoiu.conf" "$VHOST_DST"
elif [[ -f "$MIGRATE_DIR/apache-besoiu.conf" ]]; then
  cp -f "$MIGRATE_DIR/apache-besoiu.conf" "$VHOST_DST"
else
  die "Lipsește apache-besoiu.conf (repo sau $MIGRATE_DIR)"
fi
a2dissite 000-default.conf >/dev/null 2>&1 || true
a2ensite besoiu.conf >/dev/null
chown -R www-data:www-data \
  "$APP_DIR/app/Storage" \
  "$APP_DIR/app/Backend/storage" \
  "$APP_DIR/admin/storage" || true
# cod citibil de Apache
chown -R root:www-data "$APP_DIR" 2>/dev/null || true
find "$APP_DIR" -type d -exec chmod 755 {} \; 2>/dev/null || true
chmod -R ug+rwX \
  "$APP_DIR/app/Storage" \
  "$APP_DIR/app/Backend/storage" \
  "$APP_DIR/admin/storage" || true

apache2ctl configtest
systemctl reload apache2

umask 077
cat > /root/besoiu-db-credentials.txt <<EOF
DB_HOST=127.0.0.1
DB_USER=$DB_USER
DB_PASS=$DB_PASS
DB_NAME=$DB_MAIN
LEGACY_DB_NAME=$DB_LEGACY
APP_URL=$APP_URL
APP_DIR=$APP_DIR
EOF
chmod 600 /root/besoiu-db-credentials.txt

log "Gata."
log "Magazin: $APP_URL/"
log "Admin:   $APP_URL/admin/"
log "Credențiale DB: /root/besoiu-db-credentials.txt"
log "Loguri Apache: /var/log/apache2/besoiu-error.log"
