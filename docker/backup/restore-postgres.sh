#!/bin/sh
set -eu

if [ "$#" -ne 1 ]; then
    printf 'Usage: restore-postgres BACKUP_FILE\n' >&2
    exit 64
fi

backup_file=$1

if [ ! -f "${backup_file}" ]; then
    printf 'Backup file not found: %s\n' "${backup_file}" >&2
    exit 66
fi

if [ -f "${backup_file}.sha256" ]; then
    (cd "$(dirname "${backup_file}")" && sha256sum -c "$(basename "${backup_file}").sha256")
fi

pg_restore --clean --if-exists --no-owner --no-privileges --dbname="${PGDATABASE}" "${backup_file}"
