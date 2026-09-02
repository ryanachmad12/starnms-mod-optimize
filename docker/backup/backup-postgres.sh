#!/bin/sh
set -eu

backup_root=/backups
timestamp=$(date -u +%Y-%m-%d_%H%M%S)
filename="starnms_${timestamp}.dump"
temporary_file="${backup_root}/daily/.${filename}.partial"
backup_file="${backup_root}/daily/${filename}"

mkdir -p "${backup_root}/daily" "${backup_root}/weekly" "${backup_root}/monthly" "${backup_root}/verify"
rm -f "${temporary_file}"

pg_dump --format=custom --compress=9 --file="${temporary_file}"
pg_restore --list "${temporary_file}" >/dev/null
mv "${temporary_file}" "${backup_file}"
sha256sum "${backup_file}" > "${backup_file}.sha256"

day_of_week=$(date -u +%u)
day_of_month=$(date -u +%d)

if [ "${day_of_week}" = "7" ]; then
    cp "${backup_file}" "${backup_root}/weekly/starnms_weekly_$(date -u +%G-W%V).dump"
    cp "${backup_file}.sha256" "${backup_root}/weekly/starnms_weekly_$(date -u +%G-W%V).dump.sha256"
fi

if [ "${day_of_month}" = "01" ]; then
    cp "${backup_file}" "${backup_root}/monthly/starnms_monthly_$(date -u +%Y-%m).dump"
    cp "${backup_file}.sha256" "${backup_root}/monthly/starnms_monthly_$(date -u +%Y-%m).dump.sha256"
fi

printf '%s verified backup created: %s\n' "$(date -u +%FT%TZ)" "${backup_file}" >> "${backup_root}/verify/backup.log"
backup-retention
