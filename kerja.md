# Workflow Kerja Sesi Ini

Dokumen ini merangkum workflow teknis yang dipakai untuk mengerjakan dan mendeploy perubahan aplikasi `silsilah` pada sesi ini.

## 1. Lokasi Kerja

- Repo lokal: `C:\xampp\htdocs\vite\silsilah`
- Branch kerja: `master`
- Remote utama: `origin = https://github.com/fathanbeje/silsilah.git`
- Domain live: `https://syamsuri.bani.my.id`
- VPS:
  - Host: `103.177.95.140`
  - Port: `2288`
  - User: `root`
  - SSH key: `C:\Users\Administrator\.ssh\vps_deploy_ed25519`

## 2. Koneksi ke VPS

Template koneksi yang dipakai:

```powershell
ssh -i C:\Users\Administrator\.ssh\vps_deploy_ed25519 -p 2288 root@103.177.95.140
```

Untuk menjalankan satu command tanpa masuk shell interaktif:

```powershell
ssh -i C:\Users\Administrator\.ssh\vps_deploy_ed25519 -p 2288 root@103.177.95.140 "COMMAND"
```

Contoh yang dipakai pada sesi ini:

```powershell
ssh -i C:\Users\Administrator\.ssh\vps_deploy_ed25519 -p 2288 root@103.177.95.140 "/usr/local/bin/silsilah-sync"
```

## 3. Pola Kerja di Lokal

Urutan kerja yang dipakai:

1. Baca file terkait dengan `Get-Content`, `rg`, atau `Select-String`.
2. Edit file dengan patch terkontrol.
3. Verifikasi lokal:
   - lint PHP file yang diubah
   - cache Blade
   - test yang relevan
4. Commit ke Git.
5. Push ke `origin/master`.
6. Jalankan sync di VPS.
7. Smoke test domain live.

## 4. Command Verifikasi Lokal

Contoh command yang dipakai:

### Lint file PHP tertentu

```powershell
php -l C:\xampp\htdocs\vite\silsilah\app\User.php
php -l C:\xampp\htdocs\vite\silsilah\app\Http\Controllers\UsersController.php
php -l C:\xampp\htdocs\vite\silsilah\app\Services\CemeteryLocationOptions.php
```

### Cache Blade

```powershell
cd C:\xampp\htdocs\vite\silsilah
php artisan view:cache
```

### Menjalankan test yang relevan

Project ini tidak menyediakan `php artisan test`, jadi test dijalankan langsung via PHPUnit:

```powershell
cd C:\xampp\htdocs\vite\silsilah
vendor\bin\phpunit tests\Unit\UserTest.php tests\Feature\UsersProfileTest.php
```

## 5. Workflow Git

### Cek status

```powershell
git -C C:\xampp\htdocs\vite\silsilah status --short
```

### Add dan commit

```powershell
git -C C:\xampp\htdocs\vite\silsilah add app routes resources database
git -C C:\xampp\htdocs\vite\silsilah commit -m "Refine chart links and deceased metadata"
```

Contoh commit lanjutan pada sesi ini:

```powershell
git -C C:\xampp\htdocs\vite\silsilah commit -m "Fix legacy blade checked usage"
git -C C:\xampp\htdocs\vite\silsilah commit -m "Fix cemetery location option ids"
git -C C:\xampp\htdocs\vite\silsilah commit -m "Stabilize cemetery autofill fields"
```

### Push ke GitHub

```powershell
git -C C:\xampp\htdocs\vite\silsilah push origin master
```

Jika perlu memastikan HEAD lokal yang aktif benar-benar terdorong:

```powershell
git -C C:\xampp\htdocs\vite\silsilah push origin HEAD:master
```

## 6. Workflow Sync di VPS

Deploy live tidak dilakukan manual file per file. Workflow yang dipakai adalah:

1. Push perubahan ke `origin/master`
2. Jalankan script sync di VPS:

```powershell
ssh -i C:\Users\Administrator\.ssh\vps_deploy_ed25519 -p 2288 root@103.177.95.140 "/usr/local/bin/silsilah-sync"
```

### Yang dikerjakan script sync

Berdasarkan output yang terlihat pada sesi ini, script tersebut menjalankan langkah seperti:

- `git fetch`
- `git pull --ff-only`
- `composer install --no-scripts`
- `php artisan package:discover` sebagai user `frankenphp`
- `php artisan migrate` sebagai user `frankenphp`
- clear/cache ulang config, route, dan view sebagai user `frankenphp`
- restart service aplikasi

Catatan hardening:

- Jangan jalankan `php artisan view:cache`, `config:cache`, `route:cache`, `optimize`, atau `package:discover` sebagai `root` pada server live.
- Jika command Artisan dijalankan manual di VPS, gunakan:

```powershell
ssh -i C:\Users\Administrator\.ssh\vps_deploy_ed25519 -p 2288 root@103.177.95.140 "su -s /bin/bash frankenphp -c 'cd /www/wwwroot/syamsuri.bani.my.id && php artisan view:clear && php artisan view:cache'"
```

- Permission yang harus tetap dimiliki `frankenphp:frankenphp`:
  - `/www/wwwroot/syamsuri.bani.my.id/storage`
  - `/www/wwwroot/syamsuri.bani.my.id/bootstrap/cache`

## 7. Verifikasi Live

Contoh smoke test yang dipakai:

```powershell
curl.exe -I https://syamsuri.bani.my.id/
curl.exe -I https://syamsuri.bani.my.id/profile-search
curl.exe -I https://syamsuri.bani.my.id/login
curl.exe https://syamsuri.bani.my.id/profile-search/autocomplete?q=fathan
```

Untuk endpoint yang butuh login, pengecekan awal cukup memastikan response redirect ke login:

```powershell
curl.exe -I https://syamsuri.bani.my.id/users/USER_ID/edit
```

Expected awal:

- `302` ke `/login` jika belum autentikasi
- bukan `500`

## 8. Cara Debug Error Live

Saat muncul error live, langkah yang dipakai:

### Cari log Laravel di VPS

```powershell
ssh -i C:\Users\Administrator\.ssh\vps_deploy_ed25519 -p 2288 root@103.177.95.140 "find /www -type f -path '*storage/logs/*.log' 2>/dev/null | tail -n 20"
```

### Baca log aplikasi aktif

```powershell
ssh -i C:\Users\Administrator\.ssh\vps_deploy_ed25519 -p 2288 root@103.177.95.140 "tail -n 120 /www/wwwroot/syamsuri.bani.my.id/storage/logs/laravel-2026-03-12.log"
```

### Cek commit live di VPS

```powershell
ssh -i C:\Users\Administrator\.ssh\vps_deploy_ed25519 -p 2288 root@103.177.95.140 "cd /www/wwwroot/syamsuri.bani.my.id && git rev-parse HEAD"
```

## 9. Ringkasan Pola Eksekusi

Workflow praktis yang dipakai dan bisa diulang:

```powershell
cd C:\xampp\htdocs\vite\silsilah
git status --short

# edit kode

php -l .\app\User.php
php artisan view:cache
vendor\bin\phpunit tests\Unit\UserTest.php tests\Feature\UsersProfileTest.php

git add app routes resources database
git commit -m "pesan commit"
git push origin HEAD:master

ssh -i C:\Users\Administrator\.ssh\vps_deploy_ed25519 -p 2288 root@103.177.95.140 "/usr/local/bin/silsilah-sync"

curl.exe -I https://syamsuri.bani.my.id/
```

## 10. Catatan Penting

- Jangan edit kode langsung di VPS jika workflow tetap ingin mulus. VPS diasumsikan hanya `pull/sync`.
- Jika ada error live, cek log Laravel dulu sebelum menebak penyebab.
- Warning deprecation Composer/PHP 8.5 muncul saat sync, tetapi selama sesi ini sync tetap berhasil.
- Untuk perubahan schema, pastikan migration ikut ter-push sebelum sync.
- Untuk migrasi FrankenPHP worker mode, pakai file di `deploy/frankenphp/` dan worker runtime di `worker/`.
- Setelah perubahan file `worker/*.php`, `deploy/frankenphp/*`, atau config systemd/Caddyfile, restart service `frankenphp-bani-silsilah`.
- Setelah deploy manual atau recovery, normalisasi lagi ownership jika perlu:

```powershell
ssh -i C:\Users\Administrator\.ssh\vps_deploy_ed25519 -p 2288 root@103.177.95.140 "chown -R frankenphp:frankenphp /www/wwwroot/syamsuri.bani.my.id/storage /www/wwwroot/syamsuri.bani.my.id/bootstrap/cache"
```
