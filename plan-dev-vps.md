# Plan: Dev & Deploy Silsilah di VPS dengan FrankenPHP Worker

Dokumen ini menjelaskan cara deploy dan pengembangan langsung (`dev on VPS`) untuk
aplikasi Laravel `silsilah`, menggunakan FrankenPHP worker sebagai server — **tanpa proses build**.

---

## Keunggulan vs repo `src` (Vite)

| Aspek                  | `src` (Vite)              | `silsilah` (Laravel)       |
|------------------------|---------------------------|----------------------------|
| Edit PHP               | ✅ Langsung aktif          | ✅ Langsung aktif           |
| Edit Blade/HTML        | ❌ Harus `npm run build`   | ✅ Langsung aktif           |
| Edit JS                | ❌ Harus `npm run build`   | ✅ Langsung aktif           |
| Edit SCSS/CSS          | ❌ Harus `npm run build`   | ⚠️ Perlu `npm run dev` (jarang) |
| Proses build wajib     | **Ya**                    | **Tidak**                  |
| Cocok untuk dev di VPS | Tidak ideal               | **Sangat cocok**           |

> **Intinya:** Karena Laravel tidak punya proses bundling wajib seperti Vite,
> perubahan file PHP/Blade langsung terlihat di browser tanpa langkah tambahan.

---

## Prasyarat VPS

- OS: Ubuntu 22.04 / 24.04
- FrankenPHP sudah terinstall (`/usr/local/bin/frankenphp`)
- PHP 8.1+ (sudah include di FrankenPHP)
- MySQL / MariaDB berjalan
- Git terinstall
- Composer terinstall
- Port `80` dan `443` terbuka
- Domain/subdomain mengarah ke IP VPS (A record)

---

## 1. Setup Awal di VPS (sekali saja)

### 1.1 Clone repo

```bash
cd /var/www
git clone <url-repo-silsilah> silsilah
cd silsilah
```

### 1.2 Install dependensi PHP

```bash
composer install --no-dev --optimize-autoloader
```

> `--no-dev` untuk skip paket testing di production.

### 1.3 Setup `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` sesuai konfigurasi VPS:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://silsilah.mia02sgs.sch.id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=silsilah
DB_USERNAME=<user_db>
DB_PASSWORD=<password_db>
```

### 1.4 Jalankan migrasi & seeder

```bash
php artisan migrate --force
php artisan db:seed --force   # jika ada seeder data awal
```

### 1.5 Set permission

```bash
sudo chown -R www-data:www-data /var/www/silsilah
sudo chmod -R 775 /var/www/silsilah/storage
sudo chmod -R 775 /var/www/silsilah/bootstrap/cache
```

---

## 2. Konfigurasi FrankenPHP Worker (Caddyfile)

Gunakan template repo berikut sebagai basis:

- `deploy/frankenphp/Caddyfile.example`
- `deploy/frankenphp/frankenphp-silsilah.service.example`

Contoh blok aktif di VPS:

```caddyfile
:8092 {
    root * /www/wwwroot/bani-syamsuri.eu.org/public

    php_server {
        worker {
            file /www/wwwroot/bani-syamsuri.eu.org/worker/entry.php
            num {$APP_WORKER_NUM}
            env APP_ENABLE_WORKER {$APP_ENABLE_WORKER}
            env APP_WORKER_MAX_REQUESTS {$APP_WORKER_MAX_REQUESTS}
            env APP_WORKER_GC_EVERY {$APP_WORKER_GC_EVERY}
            env APP_WORKER_HEALTH_PATH {$APP_WORKER_HEALTH_PATH}
        }
    }
}
```

`php_server` tetap menangani rewrite Laravel, tetapi request dinamis sekarang masuk ke
worker `worker/entry.php` yang berjalan persisten di proses FrankenPHP.

### Restart service setelah edit Caddyfile / systemd

```bash
sudo systemctl daemon-reload
sudo frankenphp validate --config /etc/frankenphp/Caddyfile-bani-silsilah
sudo systemctl restart frankenphp-bani-silsilah
```

---

## 3. Workflow Dev Harian (tanpa build)

### Edit file dan langsung live

```bash
# SSH ke VPS
ssh user@vps

# Masuk ke direktori aplikasi
cd /var/www/silsilah

# Edit file PHP/Blade langsung
nano app/Http/Controllers/FamilyController.php
# atau pakai editor lain (vim, dll)
```

Buka browser → perubahan PHP/Blade/route **langsung aktif** di request berikutnya.

Untuk file worker (`worker/*.php`) atau config service/Caddyfile, restart service setelah perubahan.

### Update via Git (rekomendasi)

```bash
cd /var/www/silsilah
git pull origin main
```

Setelah `git pull`, jalankan sesuai kebutuhan:

| Kondisi                          | Command                            |
|----------------------------------|------------------------------------|
| Ada migrasi database baru        | `php artisan migrate --force`      |
| Ada package composer baru        | `composer install --no-dev`        |
| Ada perubahan config/route       | `php artisan optimize`             |
| Cache perlu dibersihkan          | `php artisan cache:clear`          |
| Ada perubahan `worker/*.php`     | `systemctl restart frankenphp-bani-silsilah` |
| Ada perubahan SCSS (jarang)      | `npm run prod` *(Node.js v14/16)*  |

---

## 4. Yang **Tidak** Perlu Dilakukan

- ❌ `npm run build` — tidak wajib (kecuali edit SCSS)
- ❌ Restart FrankenPHP untuk tiap edit PHP/Blade biasa
- ❌ Upload artifact/dist — langsung edit source di VPS
- ❌ Pipeline CI/CD — opsional, bukan keharusan

---

## 5. Catatan Khusus SCSS / Frontend Assets

File SCSS ada di `resources/assets/sass/`. Jika tidak ada perubahan tampilan,
**tidak perlu disentuh** — file CSS yang sudah terkompilasi ada di `public/css/app.css`.

Jika *memang* perlu edit SCSS:
1. Lakukan di lokal (bukan di VPS) dengan Node.js v14/16 (`nvm use 14`)
2. Jalankan `npm run prod`
3. Upload hanya `public/css/app.css` ke VPS via `scp` atau `git push`

---

## 6. Smoke Test Setelah Deploy

Cek endpoint berikut di browser:

1. `https://silsilah.mia02sgs.sch.id/` → halaman login
2. `https://silsilah.mia02sgs.sch.id/login` → form login
3. `https://silsilah.mia02sgs.sch.id/register` → form registrasi
4. Login dengan akun admin → dashboard tampil normal
5. Peta keluarga bisa dibuka dan diakses
6. `https://syamsuri.bani.my.id/_franken/health` → JSON health worker

---

## 7. Rollback

Jika ada masalah setelah `git pull`:

```bash
# Kembali ke commit sebelumnya
git log --oneline -5          # lihat hash commit
git checkout <hash-commit>    # atau: git reset --hard HEAD~1

# Jika ada migrasi yang perlu di-rollback
php artisan migrate:rollback
```

---

## Ringkasan

```
VPS
└── Nginx public :443
    └── reverse proxy -> 127.0.0.1:8092
        └── FrankenPHP worker service
            └── /www/wwwroot/bani-syamsuri.eu.org/public
                └── worker/entry.php

Workflow dev:
  Edit file di VPS → langsung live (no build)
  atau
  Edit lokal → git push → git pull di VPS → langsung live
```
