#!/bin/sh
set -eu

cpu_count=$(getconf _NPROCESSORS_ONLN 2>/dev/null || printf '1')
cpu_reserve=${CPU_RESERVE:-2}
monitoring_workers=${MONITORING_WORKERS:-0}

if [ "${monitoring_workers}" -le 0 ]; then
    monitoring_workers=$((cpu_count - cpu_reserve))
fi

if [ "${monitoring_workers}" -lt 1 ]; then
    monitoring_workers=1
fi

pids=''
start_workers() {
    queue=$1
    count=$2
    timeout=$3

    number=1
    while [ "${number}" -le "${count}" ]; do
        php artisan queue:work redis --queue="${queue}" --sleep=1 --tries=2 --timeout="${timeout}" --max-time=3600 --no-interaction &
        pids="${pids} $!"
        number=$((number + 1))
    done
}

stop_workers() {
    for pid in ${pids}; do
        kill -TERM "${pid}" 2>/dev/null || true
    done
    wait || true
    exit 0
}

trap stop_workers INT TERM

start_workers monitoring "${monitoring_workers}" 45
start_workers snmp "${SNMP_WORKERS:-2}" 45
start_workers default "${DEFAULT_WORKERS:-1}" 90
start_workers imports "${IMPORT_WORKERS:-1}" 300
start_workers exports "${EXPORT_WORKERS:-1}" 300

printf 'Started %s monitoring workers across %s available CPUs.\n' "${monitoring_workers}" "${cpu_count}"
wait
