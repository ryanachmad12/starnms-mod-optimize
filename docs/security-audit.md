# Security Audit

Audit date: 2026-09-02

## Remediated

| Finding | Risk | Remediation |
|---|---|---|
| Committed PostgreSQL fallback password | High | Removed the fallback password from `config/database.php`. Docker now derives Laravel `DB_PASSWORD` from the required PostgreSQL secret. |
| Login endpoint had no explicit throttling | High | Added a five-attempt-per-minute rate limit keyed by username and source IP. Failed attempts also increment the limiter and a successful login clears it. |
| Administrator could remove the last active administrator role | High | User updates now prevent deactivation or role downgrade of the final active administrator. |
| Sessions were not encrypted by default | Medium | Redis sessions now default to encryption. Docker deployment enables secure cookies. |
| Browser security headers were absent | Medium | Nginx now sets content-type, frame, referrer, permissions, and restrictive CSP headers. |
| Third-party font requests expanded browser trust boundary | Low | Removed the Google Fonts import; the UI uses local/system font stacks. |
| All services could have exposed different database passwords | High | Compose explicitly passes `POSTGRES_PASSWORD` to all Laravel services as `DB_PASSWORD`. |
| ICMP required broad privilege risk | Medium | Application and worker containers use only `NET_RAW`; they do not run in privileged mode. |
| Predictable administrator seed credential | High | The seeder now creates an administrator only when `INITIAL_ADMIN_USERNAME` and `INITIAL_ADMIN_PASSWORD` are explicitly supplied. |

## Remaining Operational Requirements

1. Set unique, long values for `POSTGRES_PASSWORD` and `APP_KEY` in the untracked `.env.docker` file.
2. Terminate TLS in Nginx or a trusted reverse proxy, set `APP_URL` to the HTTPS URL, and set `FORCE_HTTPS=true` and `SESSION_SECURE_COOKIE=true`.
3. Restrict the Nginx published port with a host firewall or trusted upstream proxy. PostgreSQL and Redis must remain private Docker services.
4. To provision the first administrator through the seeder, set unique `INITIAL_ADMIN_USERNAME` and `INITIAL_ADMIN_PASSWORD` values only for the seeding action, then remove them from the environment.
5. Keep Docker base images updated and scan built images before release.
6. Copy verified `db_backup/` data off-host. A backup only on the same server is not disaster recovery.
7. SNMP v1/v2c communities remain shared secrets stored in the application database. Use unique least-privilege communities, isolate UDP/161 by firewall, and plan SNMPv3 where network equipment supports it.
8. An existing local `.env` contains the formerly committed database password. It is outside the Docker deployment path, but it must be replaced with a unique secret or removed before any production use.

## Validation Scope

Static PHP and JavaScript syntax checks were run after the changes. Full Compose build and runtime validation require Docker on the deployment host.
