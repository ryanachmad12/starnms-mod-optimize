#!/bin/sh
set -eu

mkdir -p /backups/daily /backups/weekly /backups/monthly /backups/verify

last_run_day=''
while true; do
    current_day=$(date -u +%F)
    current_hour=$(date -u +%H)

    if [ "${current_hour}" = "${BACKUP_HOUR_UTC:-2}" ] && [ "${last_run_day}" != "${current_day}" ]; then
        if backup-postgres; then
            last_run_day="${current_day}"
        else
            printf '%s backup failed; retaining all prior verified backups\n' "$(date -u +%FT%TZ)" >> /backups/verify/backup.log
        fi
    fi

    sleep 60
done
