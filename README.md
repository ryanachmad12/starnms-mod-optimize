# NMS Starcom

Universal Network Monitoring System berbasis Laravel dan PostgreSQL untuk seluruh perangkat network berbasis IP, termasuk router, switch, firewall, server, access point, radio link, perangkat fiber, CCTV, dan IoT. Fitur utama:

- Dashboard kesehatan jaringan dan pengelompokan region/kota
- Topologi logis interaktif
- CRUD inventory perangkat
- TCP/IP availability check per perangkat atau sekaligus
- Scheduler monitoring setiap satu menit
- Riwayat hasil pengecekan pada `device_checks`
- Inventory perangkat universal dengan seed awal 23 radio dari daftar The Dude

## Menjalankan

## Production Docker

Production deployment memakai Nginx, PHP-FPM, PostgreSQL, Redis, scheduler, worker multi-core, dan backup PostgreSQL otomatis. Ikuti `docs/docker-operations.md`. Docker menjadi deployment path yang didukung untuk production; source Laravel hanya dilayani melalui folder `public/` oleh Nginx.

Security hardening and the deployment checklist are documented in `docs/security-audit.md`.

Docker build, connectivity, login, Redis, and runtime troubleshooting is documented in `FIXDOCKER.md`.

Persyaratan: PHP 8.1+ dengan ekstensi `pdo_pgsql` dan `snmp`, Composer, Node.js 18+, PostgreSQL.

1. Salin `.env.example` menjadi `.env`, lalu sesuaikan kredensial PostgreSQL.
2. Buat database `STARNMS`.
3. Jalankan `composer install`.
4. Jalankan `php artisan key:generate`.
5. Jalankan `php artisan migrate --seed`.
6. Jalankan `npm install`.
7. Jalankan `composer dev`.

Monitoring TCP memakai port per perangkat (default 80). Ubah port dari form edit jika radio menyediakan service pada port lain. Scheduler harus aktif di production dengan `php artisan schedule:work` atau cron Laravel.

## Menjalankan di Linux Mint/Ubuntu

Gunakan launcher berikut dari direktori STARNMS:

```bash
chmod +x start-nms-linux.sh
./start-nms-linux.sh
```

Launcher akan mendeteksi ekstensi SNMP. Jika belum aktif, launcher memasang paket `php<versi>-snmp` (fallback `php-snmp`) menggunakan `sudo`, mengaktifkan modul untuk PHP CLI/FPM/Apache, memverifikasi fungsi SNMP v1 (`snmpget`) dan SNMP v2c (`snmp2_get`), lalu menjalankan web pada `0.0.0.0:8000` dan Laravel scheduler.

Untuk mengecek tanpa menjalankan server:

```bash
php -r "exit(extension_loaded('snmp') && function_exists('snmpget') && function_exists('snmp2_get') ? 0 : 1);"
```

Pastikan VM dapat mengirim trafik UDP port 161 menuju perangkat yang dimonitor. SNMP v1 dan v2c berasal dari ekstensi PHP SNMP yang sama; tidak ada ekstensi terpisah untuk masing-masing versi.

### Menjalankan otomatis saat boot

Setelah launcher berhasil memuat SNMP, pasang service systemd satu kali:

```bash
chmod +x install-starnms-service-linux.sh
./install-starnms-service-linux.sh
```

Verifikasi dengan `systemctl status starnms` dan `journalctl -u starnms -f`.
