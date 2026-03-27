# Silsilah Live Workflow Reference

Use this reference with the repo's `kerja.md` when a task must follow the full git-to-production flow.

## Local Repo

- Path: `C:\xampp\htdocs\vite\silsilah`
- Main branch: `master`
- Remote: `origin = https://github.com/fathanbeje/silsilah.git`

## SSH Access

```sshconfig
Host mia02-vps
    HostName 103.177.95.140
    User root
    Port 2288
    IdentityFile C:\Users\Administrator\.ssh\vps_deploy_ed25519
    IdentitiesOnly yes
```

## Live Tenants

- Domain: `https://syamsuri.bani.my.id`
- Path: `/www/wwwroot/syamsuri.bani.my.id`
- Service: `frankenphp-syamsuri-bani-my-id.service`

- Domain: `https://salam.bani.my.id`
- Path: `/www/wwwroot/salam.bani.my.id`
- Service: `frankenphp-salam-bani-my-id.service`

- Shared photo storage: `/www/shared/silsilah/photos`

## Required Order

1. Read the relevant local files.
2. Edit locally.
3. Verify locally with the right lint or tests.
4. Run `git status --short`.
5. Run `git add`.
6. Run `git commit`.
7. Run `git push origin master`.
8. Connect to the VPS.
9. Back up the target files on both tenants.
10. Copy the changed files to both tenants.
11. Run dependency or cache commands as needed.
12. Restart both services.
13. Smoke-test both domains.

## Command Patterns

### SSH

```powershell
ssh mia02-vps
ssh mia02-vps "COMMAND"
```

### Local Verification Examples

```powershell
php -l app\Services\DeathIndexBuilder.php
php -l app\Http\Controllers\DeathsController.php
php -l routes\web.php
vendor\bin\phpunit tests\Feature\DomainFamilyScopeTest.php
vendor\bin\phpunit tests\Feature\UsersProfileTest.php
vendor\bin\phpunit tests\Feature\DeathIndexTest.php
```

### List Files in Last Commit

```powershell
git show --name-only --pretty=format: HEAD
```

### Backup Pattern

```powershell
ssh mia02-vps "mkdir -p /root/deploy-backups/syamsuri.bani.my.id-COMMIT-TIMESTAMP"
ssh mia02-vps "mkdir -p /root/deploy-backups/salam.bani.my.id-COMMIT-TIMESTAMP"
```

### Copy Pattern

```powershell
scp -P 2288 -i C:\Users\Administrator\.ssh\vps_deploy_ed25519 routes/web.php root@103.177.95.140:/www/wwwroot/syamsuri.bani.my.id/routes/web.php
scp -P 2288 -i C:\Users\Administrator\.ssh\vps_deploy_ed25519 routes/web.php root@103.177.95.140:/www/wwwroot/salam.bani.my.id/routes/web.php
```

Create directories first when the target path is new:

```powershell
ssh mia02-vps "mkdir -p /www/wwwroot/syamsuri.bani.my.id/resources/views/deaths"
ssh mia02-vps "mkdir -p /www/wwwroot/salam.bani.my.id/resources/views/deaths"
```

### Composer Install When Composer Files Changed

```powershell
ssh mia02-vps "cd /www/wwwroot/syamsuri.bani.my.id && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev"
ssh mia02-vps "cd /www/wwwroot/salam.bani.my.id && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev"
```

### Clear Cache

```powershell
ssh mia02-vps "cd /www/wwwroot/syamsuri.bani.my.id && php artisan optimize:clear"
ssh mia02-vps "cd /www/wwwroot/salam.bani.my.id && php artisan optimize:clear"
```

### Restart Services

```powershell
ssh mia02-vps "systemctl restart frankenphp-syamsuri-bani-my-id.service && systemctl is-active frankenphp-syamsuri-bani-my-id.service"
ssh mia02-vps "systemctl restart frankenphp-salam-bani-my-id.service && systemctl is-active frankenphp-salam-bani-my-id.service"
```

Expected result: `active`

### Smoke Test

```powershell
curl.exe -I https://syamsuri.bani.my.id/
curl.exe -I https://syamsuri.bani.my.id/profile-search
curl.exe -I https://syamsuri.bani.my.id/wafat
curl.exe -I "https://syamsuri.bani.my.id/wafat?tab=haul-bulan-ini"
curl.exe -I https://salam.bani.my.id/
curl.exe -I https://salam.bani.my.id/profile-search
curl.exe -I https://salam.bani.my.id/wafat
```

Minimum expected result:

- `200 OK`
- not `500`
- the page still contains the expected feature markers when deeper HTML checks are needed

## Safety Rules

- Do not rely on `git pull` in live tenant worktrees if they may be dirty.
- Do not rely on `/usr/local/bin/silsilah-sync` as the default path.
- Treat backup before copy as mandatory.
- If only one tenant is updated, the deployment is incomplete.
