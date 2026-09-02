# Docker Build And Runtime Troubleshooting

This guide covers the Docker errors encountered during STARNMS installation and the safe recovery commands.

## Docker Hub Metadata Timeout

### Error

```text
failed to resolve source metadata for docker.io/library/composer:2
net/http: timeout awaiting response headers
```

This is a connection, DNS, firewall, or Docker Hub availability issue. It is not caused by Laravel or the STARNMS Dockerfile.

### Fix

Pull required base images individually. This avoids multiple concurrent Docker Hub metadata requests:

```bash
docker pull composer:2
docker pull php:8.2-fpm-bookworm
docker pull node:20-alpine
docker pull nginx:1.27-alpine
docker pull postgres:16-alpine
docker pull redis:7-alpine
```

Then rebuild only the changed application and Nginx images:

```bash
cd /home/cc3/starnms/STARNMS
docker compose --env-file .env.docker build app nginx
docker compose --env-file .env.docker up -d --force-recreate app scheduler worker nginx
```

### Check Network Access

```bash
getent hosts registry-1.docker.io
curl -I --max-time 20 https://registry-1.docker.io/v2/
```

`HTTP/1.1 401 Unauthorized` from the `curl` command is a successful result. Docker Hub intentionally requires authentication for the registry root endpoint.

### Configure Docker DNS

If DNS lookup fails or is unreliable, create the Docker daemon configuration:

```bash
sudo mkdir -p /etc/docker
sudo nano /etc/docker/daemon.json
```

Use the following JSON:

```json
{
  "dns": ["1.1.1.1", "8.8.8.8"]
}
```

Validate the JSON, then restart Docker:

```bash
sudo systemctl restart docker
docker info
```

Retry the image pulls. If your server uses a corporate proxy or firewall, allow outbound HTTPS access to `registry-1.docker.io`, `auth.docker.io`, and Docker Hub CDN endpoints.

### Use Existing Images Temporarily

If images were built successfully previously, start them without rebuilding:

```bash
docker compose --env-file .env.docker up -d
```

This does not include source-code changes made after the last successful image build. Build again when Docker Hub connectivity returns.

## Composer Missing GD Or SNMP Extensions

### Error

```text
Root composer.json requires PHP extension ext-snmp * but it is missing
phpoffice/phpspreadsheet requires ext-gd * but it is missing
```

### Cause And Fix

Composer runs in a dependency-only build stage. The STARNMS Dockerfile intentionally ignores GD and SNMP only at that step, then compiles both extensions in the final PHP runtime image.

Build again with the current `Dockerfile`:

```bash
docker compose --env-file .env.docker build --no-cache app
```

Verify extensions in the final image:

```bash
docker compose --env-file .env.docker run --rm app php -m | grep -E 'gd|mbstring|pdo_pgsql|snmp'
```

Expected modules include:

```text
gd
mbstring
pdo_pgsql
snmp
```

## Missing Oniguruma During PHP Build

### Error

```text
ONIG_CFLAGS and ONIG_LIBS to avoid the need to call pkg-config
```

### Cause And Fix

The PHP `mbstring` extension requires the Oniguruma development library. The STARNMS Dockerfile includes `libonig-dev` and `pkg-config`.

Rebuild with the current Dockerfile:

```bash
docker compose --env-file .env.docker build --no-cache app
```

## PHP-FPM Cannot Open `/proc/self/fd/2`

### Error

```text
failed to open error_log (/proc/self/fd/2): Permission denied
FPM initialization failed
```

### Cause And Fix

PHP-FPM requires a root master process to initialize and then drops web request workers to `www-data`. The STARNMS entrypoint handles this correctly in the current version.

Rebuild and recreate the app service:

```bash
docker compose --env-file .env.docker build app
docker compose --env-file .env.docker up -d --force-recreate app scheduler worker nginx
```

Confirm PHP-FPM starts:

```bash
docker compose --env-file .env.docker logs --tail=50 app
```

Expected output:

```text
NOTICE: fpm is running
NOTICE: ready to handle connections
```

## Laravel References `NunoMaduro Collision`

### Error

```text
Class "NunoMaduro\Collision\Adapters\Laravel\CollisionServiceProvider" not found
```

### Cause And Fix

A stale local Laravel package cache was copied into the production image. It referenced a development-only package excluded by Composer's `--no-dev` install.

The current Docker build excludes `bootstrap/cache/*.php`, removes stale cache metadata, and runs package discovery within the production image.

Rebuild without cache:

```bash
docker compose --env-file .env.docker build --no-cache app
docker compose --env-file .env.docker up -d --force-recreate app scheduler worker nginx
```

Then run migrations:

```bash
make migrate
```

## PostgreSQL `relation "devices" does not exist`

### Error

```text
ERROR: relation "devices" does not exist
```

### Cause And Fix

The scheduler started before the application migrations created its tables.

Run migrations:

```bash
make migrate
```

Then verify migration state:

```bash
docker compose --env-file .env.docker exec app php artisan migrate:status
```

The scheduler will succeed on its next one-minute cycle. You may restart it after migrations if desired:

```bash
docker compose --env-file .env.docker restart scheduler worker
```

## Login Returns `419 Page Expired`

### Cause

Laravel received a CSRF token but not the matching session cookie. This commonly occurs when accessing a public IP using plain HTTP while `SESSION_SECURE_COOKIE=true` is enabled.

### Temporary HTTP/IP Configuration

Set the actual public IP in `.env.docker`:

```dotenv
APP_URL=http://YOUR-SERVER-IP
FORCE_HTTPS=false
SESSION_SECURE_COOKIE=false
```

Recreate Laravel and Nginx services:

```bash
docker compose --env-file .env.docker up -d --force-recreate app scheduler worker nginx
```

Remove site cookies for the old address or use a private browser window before retrying login.

### Production HTTPS Configuration

After TLS is configured on a DNS name, use:

```dotenv
APP_URL=https://nms.example.com
FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
```

Then recreate the same services. Do not expose an NMS login to the public internet over plain HTTP.

## Redis Memory Overcommit Warning

### Warning

```text
WARNING Memory overcommit must be enabled
```

### Fix On Docker Host

```bash
sudo sysctl -w vm.overcommit_memory=1
printf 'vm.overcommit_memory = 1\n' | sudo tee /etc/sysctl.d/99-starnms-redis.conf > /dev/null
sudo sysctl --system
```

Restart Redis if it is already running:

```bash
docker compose --env-file .env.docker restart redis
```

## Nginx Read-Only Default Configuration Notice

### Notice

```text
can not modify /etc/nginx/conf.d/default.conf (read-only file system?)
```

This is harmless. The STARNMS Nginx container is intentionally read-only as a security control. Its configuration is baked into the image and does not need the image startup script to modify it.

## Indonesia SVG Returns Nginx 404

The licensed Indonesia map is a normal public visual asset at `public/assets/indonesia.svg`. Nginx serves it directly, avoiding Laravel routing, persistent-storage volumes, and read-only mount issues. Theme treatment is applied in CSS.

Rebuild the Nginx image after changing the map:

```bash
docker compose --env-file .env.docker build nginx
docker compose --env-file .env.docker up -d --force-recreate nginx
```

Verify it:

```bash
curl -I "http://YOUR-SERVER-IP/assets/indonesia.svg?theme=dark"
```

## General Service Recovery

Show service state:

```bash
docker compose --env-file .env.docker ps
```

Show recent logs:

```bash
docker compose --env-file .env.docker logs --tail=100
```

Recreate services after environment changes:

```bash
docker compose --env-file .env.docker up -d --force-recreate
```

Never run `docker compose down -v` in production unless you deliberately intend to delete PostgreSQL and Redis persistent volumes.
