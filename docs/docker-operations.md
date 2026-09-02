# Docker Operations

## First deployment

1. Copy `.env.docker.example` to `.env.docker`.
2. Generate a Laravel application key with a trusted PHP/Laravel installation and set `APP_KEY` in `.env.docker`.
3. Replace every placeholder password. Keep `.env.docker` outside Git.
4. Set `SESSION_SECURE_COOKIE=true` and `FORCE_HTTPS=true` when the deployment is behind HTTPS, then build and start the stack with `docker compose --env-file .env.docker up -d --build`.
5. Run migrations once: `docker compose --env-file .env.docker run --rm app php artisan migrate --force`.
6. Verify application state with `docker compose --env-file .env.docker ps` and `docker compose --env-file .env.docker logs --tail=100 worker`.
7. Scale only web application containers when needed: `docker compose --env-file .env.docker up -d --scale app=2`.

Only Nginx publishes a host port. PostgreSQL and Redis remain internal to the Docker network.

For a temporary HTTP installation accessed through a public IP, set `APP_URL=http://PUBLIC-IP`, `FORCE_HTTPS=false`, and `SESSION_SECURE_COOKIE=false`. A secure cookie cannot be returned by browsers over plain HTTP, which causes Laravel login CSRF failures (`419 Page Expired`). Use HTTPS and set both `FORCE_HTTPS=true` and `SESSION_SECURE_COOKIE=true` before exposing the NMS to the internet.

The build uses a Composer-only stage for dependency download and a separate PHP runtime stage. GD and SNMP are installed and enabled in the runtime image, where the application actually executes.

The PHP-FPM master process starts as root only long enough to initialize and then runs its request workers as `www-data`; scheduler and queue processes start directly as `www-data`.

Set `vm.overcommit_memory=1` on the Docker host to avoid Redis persistence failures under memory pressure:

```bash
sudo sysctl -w vm.overcommit_memory=1
printf 'vm.overcommit_memory = 1\n' | sudo tee /etc/sysctl.d/99-starnms-redis.conf > /dev/null
sudo sysctl --system
```

The included `Makefile` wraps the safe common commands after `.env.docker` exists. For example, use `make up`, `make migrate`, `make ps`, and `make backup`.

## CPU Distribution

The `worker` service starts one ICMP monitoring worker for every online CPU minus `CPU_RESERVE`. With an 8-core host and `CPU_RESERVE=2`, it starts six ICMP workers. SNMP runs in its own bounded `snmp` queue using `SNMP_WORKERS`, while normal, import, and export queues use their separately configured worker counts.

Set `MONITORING_WORKERS` to a positive number only when a measured network/device limit is lower than the host capacity. Do not start more concurrent ICMP/SNMP probes than the monitored network can safely handle.

Scale web handling separately when needed:

```bash
docker compose --env-file .env.docker up -d --scale app=2
```

Keep exactly one `scheduler` container. Multiple scheduler instances can dispatch duplicate monitoring work.

The worker service has the minimum `NET_RAW` capability required by Linux ICMP. Do not run the application image with full privileged mode.

## Backups

`postgres-backup` creates a PostgreSQL custom-format backup at `BACKUP_HOUR_UTC`, verifies the archive with `pg_restore --list`, writes a SHA-256 checksum, then applies retention.

Backups are written to the host-visible `db_backup/` directory:

- `daily/`: retained for `DAILY_RETENTION_DAYS`, default 14.
- `weekly/`: one copy made each Sunday, retained for `WEEKLY_RETENTION_WEEKS`, default 8.
- `monthly/`: one copy made on the first day of each month, retained for `MONTHLY_RETENTION_MONTHS`, default 12.

Old backups are deleted only after a new backup is successfully created and verified. If backup creation fails, all existing verified backups remain untouched.

To inspect a backup:

```bash
docker compose --env-file .env.docker exec postgres-backup pg_restore --list /backups/daily/FILE.dump
```

To restore, stop the application services first and restore only after confirming the target database. The restore command is intentionally manual:

```bash
docker compose --env-file .env.docker exec postgres-backup restore-postgres /backups/daily/FILE.dump
```

Copy verified backup files to off-host storage. A backup kept only on this server does not protect against host or disk loss.

## Monitoring Data Retention

The scheduler runs `nms:prune-monitoring-data` each day at 03:30. It removes raw `device_checks` and `snmp_metrics` rows in PostgreSQL batches after their configured retention periods. Adjust `DEVICE_CHECK_RETENTION_DAYS` and `SNMP_METRIC_RETENTION_DAYS` before production use.
