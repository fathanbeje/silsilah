# Smoke Test FrankenPHP Worker - Silsilah

Jalankan setelah cutover worker mode selesai.

## 1. Status service

```bash
systemctl status frankenphp-bani-silsilah --no-pager -l
journalctl -u frankenphp-bani-silsilah -n 120 --no-pager
```

Expected:

- service `active (running)`
- tidak ada loop restart
- tidak ada fatal error bootstrap worker

## 2. Validasi config

```bash
frankenphp validate --config /etc/frankenphp/Caddyfile-bani-silsilah
curl -sS http://127.0.0.1:8092/_franken/health
```

Expected:

- config valid
- endpoint health mengembalikan JSON dengan `mode=frankenphp-worker`
- `handled_requests` bertambah saat endpoint dipanggil berulang

## 3. Smoke test HTTP lokal

```bash
curl -I http://127.0.0.1:8092/
curl -I http://127.0.0.1:8092/login
curl -I http://127.0.0.1:8092/profile-search
curl -sS "http://127.0.0.1:8092/profile-search/autocomplete?q=fathan"
```

Expected:

- endpoint publik bukan `500`
- login page `200`
- autocomplete tetap memberi response valid

## 4. Smoke test domain publik

```bash
curl -I https://syamsuri.bani.my.id/
curl -I https://syamsuri.bani.my.id/login
curl -I https://syamsuri.bani.my.id/profile-search
curl -sS https://syamsuri.bani.my.id/_franken/health
```

Expected:

- response publik sukses lewat Nginx reverse proxy
- header keamanan tetap ada
- endpoint health tetap menampilkan JSON worker

## 5. Verifikasi recycle worker

Jika `APP_WORKER_MAX_REQUESTS` aktif, panggil health endpoint beberapa kali dan cek apakah PID berubah setelah ambang tercapai.

```bash
for i in $(seq 1 10); do curl -sS http://127.0.0.1:8092/_franken/health; echo; done
```

Expected:

- `handled_requests` naik
- saat recycle terjadi, PID baru muncul dan counter mulai ulang
