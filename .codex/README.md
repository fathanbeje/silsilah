# Codex Skills

Repo ini menyimpan source skill Codex yang perlu portable antar komputer.

## Install

Jalankan dari root repo:

```powershell
powershell -ExecutionPolicy Bypass -File .\.codex\install-skills.ps1
```

Untuk install skill tertentu saja:

```powershell
powershell -ExecutionPolicy Bypass -File .\.codex\install-skills.ps1 -SkillNames silsilah-deploy
```

## Skill yang Disediakan

- `silsilah-deploy`: workflow git, commit, push, backup, sync dua tenant live, clear cache, restart service, dan smoke test sesuai `kerja.md`.
