#!/bin/bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCRIPT_NAME="$(basename "${BASH_SOURCE[0]}")"
DEFAULT_INSTALL_DIR="/var/www/html/Quizflow"

prompt_with_default() {
    local prompt="$1"
    local default_value="$2"
    local response

    read -r -p "$prompt [$default_value]: " response
    if [[ -z "$response" ]]; then
        response="$default_value"
    fi

    printf '%s' "$response"
}

ensure_root_dependencies() {
    sudo apt update
    sudo apt install -y curl rsync lighttpd php-fpm php-cli php-common php-mbstring php-pgsql postgresql postgresql-contrib
}

configure_lighttpd() {
    sudo lighty-enable-mod fastcgi >/dev/null 2>&1 || true

    if [[ -f /etc/lighttpd/conf-enabled/15-fastcgi-php.conf ]]; then
        sudo rm -f /etc/lighttpd/conf-enabled/15-fastcgi-php.conf
    fi

    local php_fpm_sock
    php_fpm_sock="$(find /run/php/ -maxdepth 1 -name 'php*.sock' | head -n 1)"
    if [[ -z "$php_fpm_sock" ]]; then
        echo "Kein PHP-FPM Socket gefunden. Bitte pruefen Sie die PHP-FPM Installation."
        exit 1
    fi

    cat <<EOF | sudo tee /etc/lighttpd/conf-available/15-fastcgi-php-fpm.conf >/dev/null
server.modules += ( "mod_fastcgi" )

fastcgi.server = ( ".php" =>
    ( "localhost" =>
        (
            "socket" => "$php_fpm_sock",
            "broken-scriptfilename" => "enable"
        )
    )
)
EOF

    sudo lighty-enable-mod fastcgi-php-fpm >/dev/null 2>&1 || true

    if ! sudo lighttpd -tt -f /etc/lighttpd/lighttpd.conf; then
        echo "Die Lighttpd-Konfiguration ist ungueltig."
        exit 1
    fi

    local php_fpm_service
    php_fpm_service="$(systemctl list-unit-files 'php*-fpm.service' --no-legend | awk 'NR==1 {print $1}')"
    if [[ -n "$php_fpm_service" ]]; then
        sudo systemctl enable --now "$php_fpm_service"
    fi

    sudo systemctl enable --now lighttpd
}

validate_db_identifiers() {
    local value="$1"
    local label="$2"

    if [[ ! "$value" =~ ^[A-Za-z0-9_]+$ ]]; then
        echo "$label darf nur Buchstaben, Zahlen und Unterstriche enthalten."
        exit 1
    fi
}

create_local_database() {
    local db_name="$1"
    local db_user="$2"
    local db_password="$3"
    local escaped_password="${db_password//\'/\'\'}"

    sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='${db_user}'" | grep -q 1 || \
        sudo -u postgres psql -c "CREATE USER ${db_user} WITH PASSWORD '${escaped_password}';"

    sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='${db_name}'" | grep -q 1 || \
        sudo -u postgres psql -c "CREATE DATABASE ${db_name} OWNER ${db_user};"
}

copy_application() {
    local install_dir="$1"

    sudo mkdir -p "$install_dir"
    sudo rsync -a --delete \
        --exclude '.git' \
        --exclude 'includes/core/config.php' \
        --exclude 'includes/code.txt' \
        "$SCRIPT_DIR/" "$install_dir/"
}

write_config() {
    local install_dir="$1"
    local db_server="$2"
    local db_port="$3"
    local db_name="$4"
    local db_user="$5"
    local db_password="$6"
    local admin_password_hash="$7"

    cat <<EOF | sudo tee "$install_dir/includes/core/config.php" >/dev/null
<?php
if (!defined('APP_NAME')) {
    die('Access denied');
}
const DB_TYPE = 'pgsql';
const DB_SERVER = '${db_server}';
const DB_PORT = '${db_port}';
const DB_NAME = '${db_name}';
const DB_USER = '${db_user}';
const DB_PASSWORD = '${db_password}';
const QUIZFLOW_DATA = __DIR__ . '/../questions.json';
const QUIZFLOW_CODE = __DIR__ . '/../code.txt';
const ADMIN_PASSWORD_HASH = '${admin_password_hash}';
EOF

    echo '000000' | sudo tee "$install_dir/includes/code.txt" >/dev/null
}

hash_admin_password() {
    local password="$1"
    printf '%s' "$password" | php -r '$password = stream_get_contents(STDIN); echo password_hash($password, PASSWORD_BCRYPT);'
}

run_quizflow_init() {
    local install_dir="$1"
    php "$install_dir/init.php"
}

set_permissions() {
    local install_dir="$1"
    sudo chown -R www-data:www-data "$install_dir"
    sudo find "$install_dir" -type d -exec chmod 755 {} \;
    sudo find "$install_dir" -type f -exec chmod 644 {} \;
    sudo chmod +x "$install_dir/$SCRIPT_NAME" || true
}

echo "Quizflow Installer"
echo "=================="

ensure_root_dependencies
configure_lighttpd

INSTALL_DIR="$(prompt_with_default 'Installationsverzeichnis' "$DEFAULT_INSTALL_DIR")"
DB_SERVER="$(prompt_with_default 'PostgreSQL Host' 'localhost')"
DB_PORT="$(prompt_with_default 'PostgreSQL Port' '5432')"
DB_NAME="$(prompt_with_default 'PostgreSQL Datenbankname' 'quizflow')"
DB_USER="$(prompt_with_default 'PostgreSQL Benutzername' 'quizflow')"

validate_db_identifiers "$DB_NAME" 'Datenbankname'
validate_db_identifiers "$DB_USER" 'Benutzername'

read -r -s -p "PostgreSQL Passwort: " DB_PASSWORD
echo
read -r -s -p "Admin-Passwort fuer Quizflow: " ADMIN_PASSWORD
echo
read -r -s -p "Admin-Passwort wiederholen: " ADMIN_PASSWORD_CONFIRM
echo

if [[ "$ADMIN_PASSWORD" != "$ADMIN_PASSWORD_CONFIRM" ]]; then
    echo "Die Admin-Passwoerter stimmen nicht ueberein."
    exit 1
fi

if [[ ${#ADMIN_PASSWORD} -lt 6 ]]; then
    echo "Das Admin-Passwort muss mindestens 6 Zeichen lang sein."
    exit 1
fi

if [[ "$DB_SERVER" == "localhost" || "$DB_SERVER" == "127.0.0.1" ]]; then
    CREATE_LOCAL_DB="$(prompt_with_default 'Lokale Datenbank und Benutzer automatisch anlegen? (yes/no)' 'yes')"
    if [[ "$CREATE_LOCAL_DB" == "yes" ]]; then
        create_local_database "$DB_NAME" "$DB_USER" "$DB_PASSWORD"
    fi
fi

ADMIN_PASSWORD_HASH="$(hash_admin_password "$ADMIN_PASSWORD")"

copy_application "$INSTALL_DIR"
write_config "$INSTALL_DIR" "$DB_SERVER" "$DB_PORT" "$DB_NAME" "$DB_USER" "$DB_PASSWORD" "$ADMIN_PASSWORD_HASH"
run_quizflow_init "$INSTALL_DIR"
set_permissions "$INSTALL_DIR"

sudo systemctl restart lighttpd

echo
echo "Quizflow wurde installiert."
echo "Installationspfad: $INSTALL_DIR"
echo "Login: http://$(hostname -I | awk '{print $1}')/$(basename "$INSTALL_DIR")/login.php"
echo "Quiz:  http://$(hostname -I | awk '{print $1}')/$(basename "$INSTALL_DIR")/"