#!/bin/sh
set -eu

backup_root=/backups
daily_days=${DAILY_RETENTION_DAYS:-14}
weekly_days=$(( ${WEEKLY_RETENTION_WEEKS:-8} * 7 ))
monthly_days=$(( ${MONTHLY_RETENTION_MONTHS:-12} * 31 ))

find "${backup_root}/daily" -type f \( -name '*.dump' -o -name '*.dump.sha256' \) -mtime "+${daily_days}" -delete
find "${backup_root}/weekly" -type f \( -name '*.dump' -o -name '*.dump.sha256' \) -mtime "+${weekly_days}" -delete
find "${backup_root}/monthly" -type f \( -name '*.dump' -o -name '*.dump.sha256' \) -mtime "+${monthly_days}" -delete

printf '%s backup retention completed\n' "$(date -u +%FT%TZ)" >> "${backup_root}/verify/backup.log"
