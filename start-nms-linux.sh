#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
HOST="${STARNMS_HOST:-0.0.0.0}"
PORT="${STARNMS_PORT:-8000}"

has_snmp() {
    php -r "exit(extension_loaded('snmp') && function_exists('snmpget') && function_exists('snmp2_get') ? 0 : 1);"
}

install_snmp() {
    if ! command -v apt-get >/dev/null 2>&1; then
        echo "ERROR: Ekstensi PHP SNMP belum aktif dan apt-get tidak tersedia."
        echo "Pasang ekstensi SNMP yang sesuai dengan versi PHP, lalu jalankan ulang."
        exit 1
    fi

    local php_version package
    php_version="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
    package="php${php_version}-snmp"

    echo "Ekstensi PHP SNMP belum aktif. Memasang ${package}..."
    # Repository pihak ketiga yang rusak (mis. pgAdmin lama) tidak boleh
    # menghentikan instalasi paket resmi Ubuntu yang masih tersedia.
    if ! sudo apt-get update; then
        echo "WARNING: apt-get update memiliki repository pihak ketiga yang error."
        echo "Tetap mencoba instalasi dari package index Ubuntu yang tersedia..."
    fi
    if ! sudo apt-get install -y "${package}" snmp; then
        echo "Paket ${package} tidak tersedia; mencoba paket php-snmp..."
        sudo apt-get install -y php-snmp snmp
    fi

    if command -v phpenmod >/dev/null 2>&1; then
        sudo phpenmod -v "${php_version}" snmp || sudo phpenmod snmp
    fi

    # Pastikan symlink konfigurasi CLI tersedia. phpenmod biasanya membuatnya,
    # tetapi verifikasi ini membantu pada instalasi PHP multi-versi.
    local mods_ini="/etc/php/${php_version}/mods-available/snmp.ini"
    local cli_conf="/etc/php/${php_version}/cli/conf.d/20-snmp.ini"
    if [[ -f "${mods_ini}" && ! -e "${cli_conf}" ]]; then
        sudo ln -s "${mods_ini}" "${cli_conf}"
    fi

    if command -v systemctl >/dev/null 2>&1; then
        sudo systemctl try-restart "php${php_version}-fpm.service" 2>/dev/null || true
        sudo systemctl try-restart apache2.service 2>/dev/null || true
    fi
}

command -v php >/dev/null 2>&1 || { echo "ERROR: PHP CLI belum terpasang."; exit 1; }

if ! has_snmp; then
    install_snmp
fi

if ! has_snmp; then
    echo "ERROR: Instalasi selesai tetapi PHP CLI masih belum memuat ekstensi SNMP."
    echo "PHP binary : $(command -v php)"
    echo "php.ini    : $(php --ini | sed -n 's|Loaded Configuration File: *||p')"
    echo "Periksa output: php --ini && php -m | grep -i snmp"
    exit 1
fi

if [[ "${1:-}" == "--prepare-only" ]]; then
    echo "SNMP siap dan permanen untuk PHP CLI: $(php -r 'echo PHP_VERSION;')"
    exit 0
fi

cd "${APP_DIR}"

if ! redis-cli ping >/dev/null 2>&1; then
    if command -v systemctl >/dev/null 2>&1; then sudo systemctl start redis-server 2>/dev/null || sudo systemctl start redis 2>/dev/null || true; fi
fi
redis-cli ping >/dev/null 2>&1 || { echo "ERROR: Redis tidak aktif. Jalankan redis-server atau Docker Redis."; exit 1; }

[[ -f artisan ]] || { echo "ERROR: artisan tidak ditemukan di ${APP_DIR}."; exit 1; }
[[ -f .env ]] || { echo "ERROR: File .env belum tersedia."; exit 1; }

mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views
php artisan optimize:clear

echo "SNMP siap: v1=snmpget, v2c=snmp2_get"
echo "STARNMS berjalan pada http://${HOST}:${PORT}"

php artisan schedule:work &
SCHEDULER_PID=$!
WORKERS="${STARNMS_WORKERS:-}"
if [[ -z "${WORKERS}" ]]; then
    CPU_COUNT="$(nproc 2>/dev/null || getconf _NPROCESSORS_ONLN || echo 2)"
    WORKERS=$((CPU_COUNT > 1 ? CPU_COUNT - 1 : 1))
fi
WORKER_PIDS=()
for ((i=1; i<=WORKERS; i++)); do
    php artisan queue:work "${QUEUE_CONNECTION:-redis}" --queue=monitoring --sleep=1 --tries=2 --timeout=25 --max-time=3600 &
    WORKER_PIDS+=("$!")
done
php artisan serve --host="${HOST}" --port="${PORT}" &
SERVER_PID=$!

cleanup() {
    kill "${SERVER_PID}" "${SCHEDULER_PID}" "${WORKER_PIDS[@]}" 2>/dev/null || true
    wait "${SERVER_PID}" "${SCHEDULER_PID}" "${WORKER_PIDS[@]}" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

wait -n "${SERVER_PID}" "${SCHEDULER_PID}" "${WORKER_PIDS[@]}"
