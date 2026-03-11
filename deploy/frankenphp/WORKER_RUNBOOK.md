# Worker Runbook - Silsilah

Tujuan: memindahkan `silsilah` dari mode `php_server` biasa ke FrankenPHP worker mode yang persisten.

## File repo yang dipakai

- `worker/entry.php`
- `worker/request-reset.php`
- `deploy/frankenphp/Caddyfile.example`
- `deploy/frankenphp/frankenphp-silsilah.service.example`
- `deploy/frankenphp/SMOKE_TEST_FRANKENPHP.md`

## Backup config aktif di VPS

```bash
cp /etc/frankenphp/Caddyfile-bani-silsilah /etc/frankenphp/Caddyfile-bani-silsilah.pre-worker
cp /etc/systemd/system/frankenphp-bani-silsilah.service /etc/systemd/system/frankenphp-bani-silsilah.service.pre-worker
```

## Deploy file repo terbaru

Workflow tetap mengikuti `git push` lalu sync di VPS.

Pastikan file berikut sudah ada di server:

```bash
cd /www/wwwroot/bani-syamsuri.eu.org
ls worker/entry.php worker/request-reset.php
```

## Pasang config worker

```bash
cp /www/wwwroot/bani-syamsuri.eu.org/deploy/frankenphp/Caddyfile.example /etc/frankenphp/Caddyfile-bani-silsilah
cp /www/wwwroot/bani-syamsuri.eu.org/deploy/frankenphp/frankenphp-silsilah.service.example /etc/systemd/system/frankenphp-bani-silsilah.service
systemctl daemon-reload
frankenphp validate --config /etc/frankenphp/Caddyfile-bani-silsilah
systemctl restart frankenphp-bani-silsilah
systemctl status frankenphp-bani-silsilah --no-pager -l
```

## Cutover check

1. Endpoint `http://127.0.0.1:8092/?__worker_health=1` harus `200`.
2. Domain publik `https://syamsuri.bani.my.id/?__worker_health=1` harus `200`.
3. Login, profile search, dan edit profile tidak boleh `500`.
4. Upload foto dan akses file `storage/*` harus tetap normal.

## Rollback cepat

```bash
cp /etc/frankenphp/Caddyfile-bani-silsilah.pre-worker /etc/frankenphp/Caddyfile-bani-silsilah
cp /etc/systemd/system/frankenphp-bani-silsilah.service.pre-worker /etc/systemd/system/frankenphp-bani-silsilah.service
systemctl daemon-reload
systemctl restart frankenphp-bani-silsilah
```

## Catatan teknis

- Worker ini memakai proses FrankenPHP yang persisten, tetapi Laravel diboot ulang per request.
- Pendekatan ini dipilih untuk mengurangi risiko state leak di Laravel 8 non-Octane.
- Jika nanti ingin reuse container Laravel penuh, perlu fase lanjutan dengan resetter yang lebih agresif dan uji regresi lebih ketat.
