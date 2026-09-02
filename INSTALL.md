# STARNMS Docker Installation

This guide installs STARNMS using Docker Compose. Docker is the supported production runtime. Do not run `php artisan serve`, `composer dev`, or the legacy shell/batch launchers in production.

## Requirements

- Linux host with Ubuntu 22.04+ or Debian 12+.
- A user account with `sudo` access.
- At least 4 CPU cores, 8 GB RAM, and sufficient storage for PostgreSQL and `db_backup/`.
- Network reachability from the Docker host to monitored devices:
  - ICMP for ping monitoring.
  - UDP/161 for SNMP monitoring.
- Optional but recommended: a DNS name and HTTPS certificate for production access.

## 1. Install Docker

Install Docker Engine and the Docker Compose plugin using Docker's official repository.

```bash
sudo apt update
sudo apt install -y ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc
```

For Ubuntu, add the Docker repository:

```bash
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo \"${UBUNTU_CODENAME:-$VERSION_CODENAME}\") stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
```

For Debian, use `debian` instead of `ubuntu` in the repository URL:

```bash
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/debian $(. /etc/os-release && echo \"$VERSION_CODENAME\") stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
```

Install Docker:

```bash
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo usermod -aG docker "$USER"
```

Log out and sign in again so the Docker group membership is active. Verify the installation:

```bash
docker --version
docker compose version
```

Configure the recommended Redis kernel setting. It prevents background-save failures when the host is under memory pressure:

```bash
sudo sysctl -w vm.overcommit_memory=1
printf 'vm.overcommit_memory = 1\n' | sudo tee /etc/sysctl.d/99-starnms-redis.conf > /dev/null
sudo sysctl --system
```

## 2. Prepare STARNMS

Open the project directory:

```bash
cd /home/cc3/starnms/STARNMS
```

Create the Docker environment file:

```bash
cp .env.docker.example .env.docker
chmod 600 .env.docker
```

Generate secure values:

```bash
openssl rand -base64 36
```

Generate two different values: one for PostgreSQL and one for the initial administrator password. Generate the Laravel application key after the first image build in step 4.

Edit the environment file:

```bash
nano .env.docker
```

Set the required values:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=http://SERVER-IP
FORCE_HTTPS=false

POSTGRES_DB=starnms
POSTGRES_USER=starnms
POSTGRES_PASSWORD=REPLACE_WITH_A_LONG_RANDOM_DATABASE_PASSWORD

# Docker supplies this from POSTGRES_PASSWORD to Laravel. Keep this matching value for clarity.
DB_PASSWORD=REPLACE_WITH_A_LONG_RANDOM_DATABASE_PASSWORD

INITIAL_ADMIN_USERNAME=admin
INITIAL_ADMIN_PASSWORD=REPLACE_WITH_A_UNIQUE_STRONG_ADMIN_PASSWORD

SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=false

# 0 means all online host CPUs minus CPU_RESERVE.
MONITORING_WORKERS=0
CPU_RESERVE=2
SNMP_WORKERS=2
```

Do not commit `.env.docker`. It contains production secrets and is ignored by Git.

## 3. Build Images

Build the application, Nginx, and backup images:


```bash
docker compose --env-file .env.docker build
```

Generate the Laravel key from the built application image:

```bash
docker compose --env-file .env.docker run --rm app php artisan key:generate --show
```

Copy the result to `.env.docker`:

```dotenv
APP_KEY=base64:PASTE_THE_GENERATED_VALUE_HERE
```

## 4. Start Services

Start the deployment:

```bash
make up
```

Equivalent command:

```bash
docker compose --env-file .env.docker up -d --build
```

The stack includes:

| Service | Responsibility |
|---|---|
| `nginx` | Public HTTP endpoint and static files from Laravel `public/` only. |
| `app` | Laravel PHP-FPM web application. |
| `scheduler` | One Laravel scheduler process. Do not scale it. |
| `worker` | Parallel ICMP, SNMP, import, and export queue workers. |
| `postgres` | Persistent PostgreSQL database. |
| `redis` | Queue, cache, session, and lock service. |
| `postgres-backup` | Scheduled verified PostgreSQL backups. |

Only Nginx publishes a host port. PostgreSQL and Redis are private Docker services.

## 5. Run Migrations And Create Administrator

Run database migrations once:

```bash
make migrate
```

Create the initial administrator using the values in `.env.docker`:

```bash
docker compose --env-file .env.docker run --rm app php artisan db:seed --class=AdminUserSeeder --force
```

After the account exists, remove the initial administrator password from `.env.docker`:

```dotenv
INITIAL_ADMIN_USERNAME=
INITIAL_ADMIN_PASSWORD=
```

Restart the application services so they reload configuration:

```bash
docker compose --env-file .env.docker restart app scheduler worker
```

## 6. Verify Installation

Check service state:

```bash
make ps
```

Review service logs:

```bash
make logs
```

Review queue worker logs specifically:

```bash
make worker-logs
```

Check health endpoints from the host:

```bash
curl http://localhost/health/live
curl http://localhost/health/ready
```

Expected readiness response:

```json
{"status":"ready"}
```

Open the NMS in a browser:

```text
http://SERVER-IP/
```

Sign in with the administrator account created in step 5.

### Login 419 Error On A Public IP

`419 Page Expired` after submitting login credentials means the browser did not return the Laravel session cookie needed to validate the CSRF token. This commonly happens when `SESSION_SECURE_COOKIE=true` is used with an `http://` IP address.

For a temporary HTTP/IP deployment, set the real address and disable the secure-cookie flag in `.env.docker`:

```dotenv
APP_URL=http://YOUR-SERVER-IP
FORCE_HTTPS=false
SESSION_SECURE_COOKIE=false
```

Restart all Laravel processes after changing the environment, then remove cookies for the old address or use a private browser window:

```bash
docker compose --env-file .env.docker up -d --force-recreate app scheduler worker nginx
```

Do not keep this HTTP configuration for an internet-facing production service. Configure HTTPS as described below, then set `APP_URL` to the public HTTPS domain and change both `FORCE_HTTPS` and `SESSION_SECURE_COOKIE` to `true`.

## 7. HTTPS Production Configuration

For production, place Nginx behind a trusted TLS reverse proxy or extend the Nginx service with certificates. After HTTPS is confirmed, update `.env.docker`:

```dotenv
APP_URL=https://nms.example.com
FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true
```

Restart the application services:

```bash
docker compose --env-file .env.docker restart app scheduler worker nginx
```

Allow only ports 80 and 443 through the host firewall. Do not publish PostgreSQL port 5432 or Redis port 6379.

## 8. Backups

Create and verify an immediate PostgreSQL backup:

```bash
make backup
```

Backups are written to the host-visible directory:

```text
db_backup/
├── daily/
├── weekly/
├── monthly/
└── verify/
```

Default retention:

```dotenv
DAILY_RETENTION_DAYS=14
WEEKLY_RETENTION_WEEKS=8
MONTHLY_RETENTION_MONTHS=12
```

The backup service validates an archive before applying retention. Existing verified backups are retained when a new backup fails. Copy verified backups to storage outside this host.

## 9. Worker Capacity

By default, the worker service starts ICMP workers equal to all online CPU cores minus `CPU_RESERVE`.

Example for an 8-core host:

```dotenv
MONITORING_WORKERS=0
CPU_RESERVE=2
SNMP_WORKERS=2
```

This starts six ICMP workers and two independent SNMP workers. Start with this configuration, monitor queue completion time and target-device load, then adjust `SNMP_WORKERS` or set an explicit `MONITORING_WORKERS` maximum if required.

Scale PHP-FPM web containers separately when browser traffic needs it:

```bash
docker compose --env-file .env.docker up -d --scale app=2
```

Never scale `scheduler` above one container. Multiple schedulers can dispatch duplicate monitoring work.

## 10. Upgrade Procedure

1. Confirm a current backup exists:

```bash
make backup
```

2. Build and start the updated images:

```bash
docker compose --env-file .env.docker up -d --build
```

3. Apply migrations once:

```bash
make migrate
```

4. Check logs and health:

```bash
make ps
make worker-logs
curl http://localhost/health/ready
```

## 11. Restore Procedure

Restoration overwrites database state. Stop application services first and identify the exact verified backup file.

```bash
docker compose --env-file .env.docker stop nginx app scheduler worker
docker compose --env-file .env.docker exec postgres-backup restore-postgres /backups/daily/FILE.dump
docker compose --env-file .env.docker start app scheduler worker nginx
```

Verify the restored system, then check the health endpoint and worker logs.

## Daily Operations

```bash
make ps           # Show service status
make logs         # Follow all logs
make worker-logs  # Follow worker logs
make backup       # Create a verified backup now
make restart      # Restart services
make down         # Stop services without deleting volumes
```

Do not use `docker compose down -v` in production unless you intentionally want to remove persistent PostgreSQL and Redis volumes.

## Security Checklist

- Keep `.env.docker` permissioned to the deployment account only: `chmod 600 .env.docker`.
- Use a unique `POSTGRES_PASSWORD`, `APP_KEY`, and administrator password.
- Set `APP_DEBUG=false`.
- Enable `FORCE_HTTPS=true` and `SESSION_SECURE_COOKIE=true` after HTTPS is active.
- Keep PostgreSQL and Redis private to Docker networking.
- Copy `db_backup/` to off-host storage.
- Use unique, least-privilege SNMP communities and firewall UDP/161.
- Review `docs/security-audit.md` before production release.
