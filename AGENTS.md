# Repo Workflow

- This repository has a mandatory workflow. For any task that mentions `kerja.md`, `workflow`, `deploy`, `push`, `sync`, `VPS`, `2 subdomain`, or `dua tenant`, read `kerja.md` before doing substantial work.
- Work in the local repo at `C:\xampp\htdocs\vite\silsilah`. Do not edit live tenant files directly except for the deployment copy steps described in `kerja.md`.
- Unless the user explicitly says otherwise, after changing repo files run the relevant local verification, then `git status --short`, `git add`, `git commit`, and `git push origin master`.
- If a change is intended for the live application, both tenants are mandatory: `syamsuri.bani.my.id` and `salam.bani.my.id`.
- Before copying anything to live, back up the target files on both tenants.
- Default live sync is manual file copy of the changed files. Do not rely on `git pull` in live tenant worktrees.
- After live sync, run `composer install` on both tenants if `composer.json` or `composer.lock` changed. Always run `php artisan optimize:clear`, restart both frankenphp services, and smoke-test both live domains.
- Use the exact host, paths, service names, and command patterns from `kerja.md`.
- The portable source for the Codex workflow skill lives at `.codex/skills/silsilah-deploy`. If the global skill is not installed on the current machine, use the repo copy as the source of truth and install it with `.codex/install-skills.ps1`.
- For docs-only changes or local AI configuration changes, git commit and push still apply, but VPS sync is not required unless the user explicitly asks for it.
