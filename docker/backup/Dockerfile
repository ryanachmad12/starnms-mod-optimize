FROM postgres:16-alpine

COPY backup-postgres.sh /usr/local/bin/backup-postgres
COPY retention.sh /usr/local/bin/backup-retention
COPY restore-postgres.sh /usr/local/bin/restore-postgres
COPY entrypoint.sh /usr/local/bin/backup-entrypoint

RUN chmod +x /usr/local/bin/backup-postgres /usr/local/bin/backup-retention /usr/local/bin/restore-postgres /usr/local/bin/backup-entrypoint

ENTRYPOINT ["backup-entrypoint"]
