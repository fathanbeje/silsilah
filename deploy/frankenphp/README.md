# FrankenPHP Worker Support - Silsilah

Direktori ini berisi template dan runbook untuk menjalankan `silsilah` dengan FrankenPHP worker mode.

## Isi

- `Caddyfile.example`
- `frankenphp-silsilah.service.example`
- `SMOKE_TEST_FRANKENPHP.md`
- `WORKER_RUNBOOK.md`

## Karakter implementasi

- Proses FrankenPHP persisten
- Worker health endpoint di `/_franken/health`
- Laravel diboot ulang per request untuk menjaga isolasi state
- Cocok sebagai langkah pertama migrasi dari `php_server` biasa ke worker mode

## Catatan

Implementasi ini sengaja tidak memaksakan reuse container Laravel penuh seperti Octane. Fokusnya adalah mendapatkan worker mode yang stabil dulu di production, baru setelah itu dievaluasi apakah perlu optimasi resetter yang lebih agresif.
