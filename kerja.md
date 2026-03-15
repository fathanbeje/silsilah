# Workflow Kerja `silsilah`

Dokumen ini adalah workflow kerja yang harus dipakai saat mengubah aplikasi `silsilah`, termasuk login VPS, commit Git, push GitHub, dan sync ke dua tenant live.

## 1. Lokasi Kerja

- Repo lokal: `C:\xampp\htdocs\vite\silsilah`
- Branch kerja utama: `master`
- Remote utama: `origin = https://github.com/fathanbeje/silsilah.git`
- Tenant live aktif:
  - `https://syamsuri.bani.my.id`
  - `https://salam.bani.my.id`
- Canonical deploy path di VPS:
  - `/www/wwwroot/syamsuri.bani.my.id`
  - `/www/wwwroot/salam.bani.my.id`
- Service live:
  - `frankenphp-syamsuri-bani-my-id.service`
  - `frankenphp-salam-bani-my-id.service`
- Shared storage foto:
  - `/www/shared/silsilah/photos`

## 2. Akses VPS

### SSH config yang dipakai

```sshconfig
Host mia02-vps
    HostName 103.177.95.140
    User root
    Port 2288
    IdentityFile C:\Users\Administrator\.ssh\vps_deploy_ed25519
    IdentitiesOnly yes
```

### Login interaktif

```powershell
ssh mia02-vps
```

Atau tanpa SSH config:

```powershell
ssh -i C:\Users\Administrator\.ssh\vps_deploy_ed25519 -p 2288 root@103.177.95.140
```

### Menjalankan command sekali jalan

```powershell
ssh mia02-vps "COMMAND"
```

Contoh:

```powershell
ssh mia02-vps "systemctl is-active frankenphp-syamsuri-bani-my-id.service"
ssh mia02-vps "cd /www/wwwroot/syamsuri.bani.my.id && git rev-parse HEAD"
```

## 3. Urutan Kerja Wajib

Urutan kerja yang harus diikuti:

1. Baca file terkait di repo lokal.
2. Edit file di lokal, jangan edit langsung di VPS.
3. Verifikasi lokal:
   - `php -l` untuk file PHP yang berubah
   - PHPUnit yang relevan
4. `git status --short`
5. `git add ...`
6. `git commit -m "..."`
7. `git push origin master`
8. Login / eksekusi command ke VPS
9. Backup file target di dua tenant
10. Sync perubahan ke dua tenant
11. Jalankan `composer install`, `php artisan optimize:clear`, restart service tenant
12. Smoke test domain live

## 4. Verifikasi Lokal

### Contoh lint

```powershell
cd C:\xampp\htdocs\vite\silsilah
php -l app\Services\DeathIndexBuilder.php
php -l app\Http\Controllers\DeathsController.php
php -l routes\web.php
```

### Menjalankan test

Project ini memakai PHPUnit langsung:

```powershell
cd C:\xampp\htdocs\vite\silsilah
vendor\bin\phpunit tests\Feature\DomainFamilyScopeTest.php
vendor\bin\phpunit tests\Feature\UsersProfileTest.php
vendor\bin\phpunit tests\Feature\DeathIndexTest.php
```

## 5. Workflow Git

### Cek status

```powershell
git -C C:\xampp\htdocs\vite\silsilah status --short
```

### Add dan commit

```powershell
git -C C:\xampp\htdocs\vite\silsilah add app routes resources tests composer.json composer.lock kerja.md
git -C C:\xampp\htdocs\vite\silsilah commit -m "Pesan commit"
```

### Push

```powershell
git -C C:\xampp\htdocs\vite\silsilah push origin master
```

Jika ingin eksplisit mendorong HEAD aktif:

```powershell
git -C C:\xampp\htdocs\vite\silsilah push origin HEAD:master
```

## 6. Workflow Sync ke VPS

### Prinsip penting

- Jangan mengandalkan `git pull` langsung di repo tenant live jika worktree server dirty.
- Jangan mengandalkan `/usr/local/bin/silsilah-sync` untuk semua kasus.
- Sync default yang aman adalah:
  - backup file target
  - copy file hasil commit
  - update dependency bila perlu
  - clear cache
  - restart service

Alasan:

- Repo tenant live sering punya perubahan lokal / file dirty.
- Script `/usr/local/bin/silsilah-sync` bisa tertinggal konfigurasi lama atau hanya menunjuk satu tenant.
- Tenant yang harus disinkronkan selalu dua:
  - `syamsuri.bani.my.id`
  - `salam.bani.my.id`

### 6.1. Siapkan daftar file dari commit terakhir

```powershell
cd C:\xampp\htdocs\vite\silsilah
git show --name-only --pretty=format: HEAD
```

### 6.2. Backup file target di VPS

Contoh pola aman:

```powershell
ssh mia02-vps "mkdir -p /root/deploy-backups/syamsuri.bani.my.id-COMMIT-TIMESTAMP"
ssh mia02-vps "mkdir -p /root/deploy-backups/salam.bani.my.id-COMMIT-TIMESTAMP"
```

Jika perlu backup file tertentu:

```powershell
ssh mia02-vps "cp /www/wwwroot/syamsuri.bani.my.id/routes/web.php /root/deploy-backups/syamsuri.bani.my.id-COMMIT-TIMESTAMP/routes-web.php"
ssh mia02-vps "cp /www/wwwroot/salam.bani.my.id/routes/web.php /root/deploy-backups/salam.bani.my.id-COMMIT-TIMESTAMP/routes-web.php"
```

### 6.3. Copy file hasil perubahan ke tenant live

Contoh:

```powershell
scp -P 2288 -i C:\Users\Administrator\.ssh\vps_deploy_ed25519 routes/web.php root@103.177.95.140:/www/wwwroot/syamsuri.bani.my.id/routes/web.php
scp -P 2288 -i C:\Users\Administrator\.ssh\vps_deploy_ed25519 routes/web.php root@103.177.95.140:/www/wwwroot/salam.bani.my.id/routes/web.php
```

Untuk file baru, pastikan directory target dibuat dulu:

```powershell
ssh mia02-vps "mkdir -p /www/wwwroot/syamsuri.bani.my.id/resources/views/deaths"
ssh mia02-vps "mkdir -p /www/wwwroot/salam.bani.my.id/resources/views/deaths"
```

### 6.4. Install dependency jika `composer.json` atau `composer.lock` berubah

Jalankan di masing-masing tenant:

```powershell
ssh mia02-vps "cd /www/wwwroot/syamsuri.bani.my.id && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev"
ssh mia02-vps "cd /www/wwwroot/salam.bani.my.id && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev"
```

### 6.5. Clear cache aplikasi

```powershell
ssh mia02-vps "cd /www/wwwroot/syamsuri.bani.my.id && php artisan optimize:clear"
ssh mia02-vps "cd /www/wwwroot/salam.bani.my.id && php artisan optimize:clear"
```

### 6.6. Restart service tenant

```powershell
ssh mia02-vps "systemctl restart frankenphp-syamsuri-bani-my-id.service && systemctl is-active frankenphp-syamsuri-bani-my-id.service"
ssh mia02-vps "systemctl restart frankenphp-salam-bani-my-id.service && systemctl is-active frankenphp-salam-bani-my-id.service"
```

Expected:

- output `active`

## 7. Smoke Test Live

Minimal setelah deploy:

```powershell
curl.exe -I https://syamsuri.bani.my.id/
curl.exe -I https://syamsuri.bani.my.id/profile-search
curl.exe -I https://syamsuri.bani.my.id/wafat
curl.exe -I "https://syamsuri.bani.my.id/wafat?tab=haul-bulan-ini"

curl.exe -I https://salam.bani.my.id/
curl.exe -I https://salam.bani.my.id/profile-search
curl.exe -I https://salam.bani.my.id/wafat
```

Jika perlu cek marker HTML:

```powershell
curl.exe -L https://syamsuri.bani.my.id/wafat
curl.exe -L https://salam.bani.my.id/wafat
```

Expected awal:

- `200 OK`
- bukan `500`
- halaman memuat marker atau teks fitur baru yang diharapkan

## 8. Debug Live

### Cek status service

```powershell
ssh mia02-vps "systemctl status frankenphp-syamsuri-bani-my-id.service --no-pager -n 40"
ssh mia02-vps "systemctl status frankenphp-salam-bani-my-id.service --no-pager -n 40"
```

### Cari log Laravel

```powershell
ssh mia02-vps "find /www -type f -path '*storage/logs/*.log' 2>/dev/null | tail -n 20"
```

### Baca log tenant tertentu

```powershell
ssh mia02-vps "tail -n 120 /www/wwwroot/syamsuri.bani.my.id/storage/logs/laravel-$(date +%F).log"
ssh mia02-vps "tail -n 120 /www/wwwroot/salam.bani.my.id/storage/logs/laravel-$(date +%F).log"
```

### Cek commit live

```powershell
ssh mia02-vps "cd /www/wwwroot/syamsuri.bani.my.id && git rev-parse HEAD"
ssh mia02-vps "cd /www/wwwroot/salam.bani.my.id && git rev-parse HEAD"
```

Catatan: `git rev-parse HEAD` di tenant live hanya berguna sebagai referensi. Saat deploy dilakukan via copy file manual, repo live bisa saja tidak sepenuhnya mencerminkan source terbaru.

## 9. Workflow Praktis Singkat

```powershell
cd C:\xampp\htdocs\vite\silsilah
git status --short

# edit kode

php -l app\Services\DeathIndexBuilder.php
vendor\bin\phpunit tests\Feature\DeathIndexTest.php

git add app routes resources tests composer.json composer.lock kerja.md
git commit -m "Pesan commit"
git push origin master

ssh mia02-vps "mkdir -p /root/deploy-backups/syamsuri-COMMIT"
ssh mia02-vps "mkdir -p /root/deploy-backups/salam-COMMIT"

# copy file hasil commit ke:
# /www/wwwroot/syamsuri.bani.my.id
# /www/wwwroot/salam.bani.my.id

ssh mia02-vps "cd /www/wwwroot/syamsuri.bani.my.id && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev && php artisan optimize:clear"
ssh mia02-vps "cd /www/wwwroot/salam.bani.my.id && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev && php artisan optimize:clear"

ssh mia02-vps "systemctl restart frankenphp-syamsuri-bani-my-id.service && systemctl is-active frankenphp-syamsuri-bani-my-id.service"
ssh mia02-vps "systemctl restart frankenphp-salam-bani-my-id.service && systemctl is-active frankenphp-salam-bani-my-id.service"

curl.exe -I https://syamsuri.bani.my.id/wafat
curl.exe -I https://salam.bani.my.id/wafat
```

## 10. Catatan Penting

- Tenant live yang wajib disinkronkan selalu dua, bukan satu:
  - `syamsuri.bani.my.id`
  - `salam.bani.my.id`
- Canonical path `syamsuri` adalah `/www/wwwroot/syamsuri.bani.my.id`, bukan `bani-syamsuri.eu.org`.
- Jika ada symlink / config lama, jangan jadikan itu acuan workflow baru.
- Jika `composer.json` / `composer.lock` berubah, selalu jalankan `composer install` di dua tenant.
- Jika hanya file view/controller/routes berubah, tetap jalankan `php artisan optimize:clear` dan restart service.
- Jangan melakukan destructive reset di VPS pada repo tenant live.
- Backup sebelum copy file wajib dilakukan.
- Untuk foto lintas tenant, source of truth storage ada di shared path:
  - `/www/shared/silsilah/photos`
