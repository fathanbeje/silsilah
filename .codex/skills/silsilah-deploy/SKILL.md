---
name: silsilah-deploy
description: Use for the silsilah repo when the user asks to follow kerja.md or to handle the required git and VPS workflow: verify locally, commit, push, back up both tenants, sync both live subdomains, clear caches, restart services, and smoke-test both domains.
---

# Silsilah Deploy

## Overview

Use this skill for work in the `silsilah` repository when the user explicitly asks to follow `kerja.md`, deploy changes, sync the VPS, update both live subdomains, or run the full git-to-production workflow.

Read the repo's `AGENTS.md` and `kerja.md` first, then execute the workflow end to end unless the user narrows the scope.

## Trigger Phrases

Use this skill when the request mentions any of these ideas:

- `kerja.md`
- `workflow`
- `deploy`
- `push`
- `sync`
- `VPS`
- `dua subdomain`
- `dua tenant`
- `syamsuri.bani.my.id`
- `salam.bani.my.id`

## Workflow

1. Read `AGENTS.md` and `kerja.md` in the repo.
2. Inspect the changed files and decide whether the task is docs-only, local-only, or intended for the live runtime.
3. Complete the work locally. Do not edit the live tenant files directly.
4. Run local verification appropriate to the touched files.
5. If repo files changed, run `git status --short`, `git add`, `git commit`, and `git push origin master` unless the user explicitly says not to.
6. If the change is intended for live runtime, follow `references/workflow.md`:
   - back up target files on both tenants
   - sync the changed files to both tenant paths
   - run `composer install` on both tenants if composer files changed
   - run `php artisan optimize:clear` on both tenants
   - restart both frankenphp services and confirm `active`
   - smoke-test both live domains
7. Report the commit hash, backup locations, deployed files, service status, and smoke-test results. If deployment was intentionally skipped, say why.

## Rules

- Treat the two live tenants as mandatory peers. Do not deploy only one unless the user explicitly instructs it.
- Use manual file copy as the safe default for live sync. Do not rely on `git pull` in dirty live worktrees.
- Never say deployment is finished until backup, sync, cache clear, restart, and smoke tests have completed.
- If a step fails, stop, preserve the current state, and report the exact blocker.
- Use `references/workflow.md` for the exact host, paths, services, and command patterns.
