-- Intracom NMS PostgreSQL schema
-- Jalankan pada database "STARNMS" melalui pgAdmin Query Tool.

BEGIN;

CREATE TABLE IF NOT EXISTS devices (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    ip_address INET NOT NULL UNIQUE,
    mac_address VARCHAR(32),
    type VARCHAR(80) NOT NULL DEFAULT 'INTRAKOM BS',
    region VARCHAR(40) NOT NULL,
    city VARCHAR(80),
    latitude NUMERIC(10,7),
    longitude NUMERIC(10,7),
    parent_id BIGINT REFERENCES devices(id) ON DELETE SET NULL,
    monitor_port INTEGER NOT NULL DEFAULT 80 CHECK (monitor_port BETWEEN 1 AND 65535),
    web_protocol VARCHAR(8) NOT NULL DEFAULT 'http' CHECK (web_protocol IN ('http','https')),
    web_port INTEGER NOT NULL DEFAULT 80 CHECK (web_port BETWEEN 1 AND 65535),
    telnet_port INTEGER NOT NULL DEFAULT 23 CHECK (telnet_port BETWEEN 1 AND 65535),
    ssh_port INTEGER NOT NULL DEFAULT 22 CHECK (ssh_port BETWEEN 1 AND 65535),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    status VARCHAR(16) NOT NULL DEFAULT 'unknown'
        CHECK (status IN ('online', 'offline', 'unknown')),
    latency_ms INTEGER,
    last_checked_at TIMESTAMPTZ,
    last_seen_at TIMESTAMPTZ,

    snmp_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    snmp_version VARCHAR(8) NOT NULL DEFAULT '2c',
    snmp_community VARCHAR(100) NOT NULL DEFAULT 'public',
    snmp_port INTEGER NOT NULL DEFAULT 161 CHECK (snmp_port BETWEEN 1 AND 65535),
    snmp_timeout_ms SMALLINT NOT NULL DEFAULT 1200
        CHECK (snmp_timeout_ms BETWEEN 100 AND 10000),
    snmp_oids JSONB,
    snmp_last_polled_at TIMESTAMPTZ,
    snmp_status VARCHAR(16) NOT NULL DEFAULT 'disabled'
        CHECK (snmp_status IN ('disabled', 'unknown', 'online', 'timeout', 'error')),

    created_at TIMESTAMPTZ,
    updated_at TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS devices_region_index ON devices(region);
CREATE INDEX IF NOT EXISTS devices_status_index ON devices(status);
CREATE INDEX IF NOT EXISTS devices_parent_id_index ON devices(parent_id);
CREATE INDEX IF NOT EXISTS devices_snmp_enabled_index ON devices(snmp_enabled);

CREATE TABLE IF NOT EXISTS device_checks (
    id BIGSERIAL PRIMARY KEY,
    device_id BIGINT NOT NULL REFERENCES devices(id) ON DELETE CASCADE,
    status VARCHAR(16) NOT NULL,
    latency_ms INTEGER,
    message VARCHAR(255),
    checked_at TIMESTAMPTZ NOT NULL
);

CREATE INDEX IF NOT EXISTS device_checks_checked_at_index
    ON device_checks(checked_at);
CREATE INDEX IF NOT EXISTS device_checks_device_time_index
    ON device_checks(device_id, checked_at);

CREATE TABLE IF NOT EXISTS snmp_metrics (
    id BIGSERIAL PRIMARY KEY,
    device_id BIGINT NOT NULL REFERENCES devices(id) ON DELETE CASCADE,
    oid VARCHAR(160) NOT NULL,
    label VARCHAR(100) NOT NULL,
    numeric_value DOUBLE PRECISION,
    raw_value TEXT,
    data_type VARCHAR(30),
    polled_at TIMESTAMPTZ NOT NULL
);

CREATE INDEX IF NOT EXISTS snmp_metrics_polled_at_index
    ON snmp_metrics(polled_at);
CREATE INDEX IF NOT EXISTS snmp_metrics_device_oid_time_index
    ON snmp_metrics(device_id, oid, polled_at);

-- Digunakan Laravel untuk mencatat migration yang telah dijalankan.
CREATE TABLE IF NOT EXISTS migrations (
    id SERIAL PRIMARY KEY,
    migration VARCHAR(255) NOT NULL,
    batch INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    username VARCHAR(60) NOT NULL UNIQUE,
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user' CHECK (role IN ('administrator','user')),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at TIMESTAMPTZ,
    remember_token VARCHAR(100),
    created_at TIMESTAMPTZ,
    updated_at TIMESTAMPTZ
);
CREATE INDEX IF NOT EXISTS users_role_index ON users(role);

CREATE TABLE IF NOT EXISTS projects (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(180) NOT NULL,
    customer_name VARCHAR(180) NOT NULL,
    contract_start_date DATE NOT NULL,
    contract_end_date DATE NOT NULL,
    location VARCHAR(255) NOT NULL,
    maintenance_interval_months SMALLINT NOT NULL CHECK (maintenance_interval_months IN (3,6,12)),
    maintenance_anchor_date DATE NOT NULL,
    notes TEXT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ,
    updated_at TIMESTAMPTZ,
    CHECK (contract_end_date >= contract_start_date)
);
CREATE INDEX IF NOT EXISTS projects_contract_active_index ON projects(contract_end_date,is_active);

ALTER TABLE devices ADD COLUMN IF NOT EXISTS project_id BIGINT;
DO $$ BEGIN
    ALTER TABLE devices ADD CONSTRAINT devices_project_id_foreign FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL; END $$;
CREATE INDEX IF NOT EXISTS devices_project_id_index ON devices(project_id);

INSERT INTO migrations (migration, batch)
SELECT migration, 1
FROM (VALUES
    ('2026_03_02_000001_create_devices_table'),
    ('2026_03_02_000002_create_device_checks_table'),
    ('2026_07_31_000003_add_snmp_to_devices_table'),
    ('2026_07_31_000004_create_snmp_metrics_table'),
    ('2026_07_31_000005_create_users_table'),
    ('2026_07_31_000006_add_remote_ports_to_devices_table'),
    ('2026_07_31_000007_create_projects_table'),
    ('2026_07_31_000008_add_project_id_to_devices_table')
) AS required_migrations(migration)
WHERE NOT EXISTS (
    SELECT 1 FROM migrations existing
    WHERE existing.migration = required_migrations.migration
);

-- Data awal perangkat dari NMS The Dude.
INSERT INTO devices
    (name, ip_address, type, region, city, created_at, updated_at)
VALUES
    ('ODU INTRAKOM PULO BRAYAN', '10.162.60.6', 'INTRAKOM BS', 'MDN', 'Medan', NOW(), NOW()),
    ('ODU INTRAKOM OTISTA MERDEKA', '10.162.75.104', 'INTRAKOM BS', 'JKT', 'Jakarta', NOW(), NOW()),
    ('ODU INTRAKOM GENDUNGAN GILIGENTING', '10.161.25.58', 'INTRAKOM BS', 'MDUR', 'Madura', NOW(), NOW()),
    ('ODU INTRAKOM AIRPORT PONTIANAK', '10.161.60.53', 'INTRAKOM BS', 'PTK', 'Pontianak', NOW(), NOW()),
    ('ODU INTRAKOM TANJUNG RAYA', '10.161.60.103', 'INTRAKOM BS', 'PTK', 'Pontianak', NOW(), NOW()),
    ('ODU INTRAKOM SIDOYOSO', '10.162.57.5', 'INTRAKOM BS', 'SBY', 'Surabaya', NOW(), NOW()),
    ('ODU INTRAKOM KELANDIS', '10.162.59.8', 'INTRAKOM BS', 'DPS', 'Denpasar', NOW(), NOW()),
    ('ODU INTRAKOM IMAM BONJOL', '10.162.60.154', 'INTRAKOM BS', 'MDN', 'Medan', NOW(), NOW()),
    ('ODU INTRAKOM RUNGKUT', '10.162.68.57', 'INTRAKOM BS', 'SBY', 'Surabaya', NOW(), NOW()),
    ('ODU INTRAKOM KAMPUNG JAWA', '10.162.82.153', 'INTRAKOM BS', 'SMD', 'Samarinda', NOW(), NOW()),
    ('MU 2 TELRAD JELAMBAR', '10.162.32.6', 'TELRAD BS', 'JKRT', 'Jakarta', NOW(), NOW()),
    ('MU 3 TELRAD JELAMBAR', '10.162.32.7', 'TELRAD BS', 'JKRT', 'Jakarta', NOW(), NOW()),
    ('MU 1 TELRAD CIBIRU', '10.162.48.101', 'TELRAD BS', 'BDG', 'Bandung', NOW(), NOW()),
    ('MU 1 TELRAD CILEUNYI', '10.162.48.104', 'TELRAD BS', 'BDG', 'Bandung', NOW(), NOW()),
    ('MU 1 TELRAD MENUR', '10.162.57.76', 'TELRAD BS', 'SBY', 'Surabaya', NOW(), NOW()),
    ('MU 1 TELRAD KARANG AYU', '10.162.63.104', 'TELRAD BS', 'SMR', 'Semarang', NOW(), NOW()),
    ('MU 1 TELRAD DATACOM', '10.161.37.10', 'TELRAD BS', 'PLM', 'Palembang', NOW(), NOW()),
    ('MU 1 TELRAD PATHUK', '10.161.74.14', 'TELRAD BS', 'YGY', 'Yogyakarta', NOW(), NOW()),
    ('MU 1 TELRAD JELAMBAR', '10.162.32.5', 'TELRAD BS', 'JKRT', 'Jakarta', NOW(), NOW()),
    ('MU 2 TELRAD CIBIRU', '10.162.48.102', 'TELRAD BS', 'BDG', 'Bandung', NOW(), NOW()),
    ('MU 1 TELRAD NGALIYAN', '10.162.63.6', 'TELRAD BS', 'SMR', 'Semarang', NOW(), NOW()),
    ('MU 1 TELRAD PADALARANG', '10.162.71.6', 'TELRAD BS', 'BDG', 'Bandung', NOW(), NOW()),
    ('MU 1 TELRAD GUNUNG BALAU', '10.162.78.62', 'TELRAD BS', 'LMP', 'Lampung', NOW(), NOW())
ON CONFLICT (ip_address) DO UPDATE SET
    name = EXCLUDED.name,
    type = EXCLUDED.type,
    region = EXCLUDED.region,
    city = EXCLUDED.city,
    updated_at = NOW();

COMMIT;

-- Verifikasi hasil:
SELECT
    (SELECT COUNT(*) FROM devices) AS total_devices,
    (SELECT COUNT(*) FROM device_checks) AS total_tcp_checks,
    (SELECT COUNT(*) FROM snmp_metrics) AS total_snmp_metrics;
