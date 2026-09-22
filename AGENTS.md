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

# Taste Skill & Anti-Slop Frontend Standards

Whenever designing, generating, refactoring, or styling any frontend user interface, landing page, component, web page, or visual element (HTML, CSS, Tailwind, JS/TS, React, Vue, Svelte, PHP, etc.), the agent MUST STRICTLY ADHERE to the **Taste Skill** guidelines (`design-taste-frontend`).

## Mandatory Protocol

### 1. Step 0: Brief Inference & Design Read (Mandatory)
Before writing any frontend code, output a one-line Design Read:
`Reading this as: <page kind> for <audience>, with a <vibe> language, leaning toward <design system or aesthetic family>.`
- Read room signals first: Page kind, vibe words, reference signals, audience, existing brand assets, and quiet constraints.
- If the brief is ambiguous, ask at most ONE clarifying question before guessing.

### 2. Step 1: Declare the Three Dials
Explicitly calibrate and declare the three dials:
- `DESIGN_VARIANCE` (1-10): Layout asymmetry and modern visual interest (Baseline: 8).
- `MOTION_INTENSITY` (1-10): Animation depth and physics (Baseline: 6).
- `VISUAL_DENSITY` (1-10): Information per viewport (Baseline: 4).

### 3. Anti-Slop Bans (Zero Tolerance)
- NEVER generate default AI-purple/violet gradients or neon glow blobs behind cards.
- NEVER default to 3 equal-column feature cards with centered icons.
- NEVER default to Inter + slate-900 / zinc-900 dark theme unless explicitly requested.
- NEVER apply cookie-cutter glassmorphism (`backdrop-blur`) indiscriminately.
- NEVER use em-dashes (—) in UI copy or marketing headlines.
- NEVER output placeholder comments (`// ... rest of code`, `/* styles here */`); output complete, production-ready code.

### 4. Available Taste Skills Reference
Consult the installed skill documentation in `.agents/skills/` or `~/.agents/skills/`:
- `design-taste-frontend`: Core v2 framework, layout variance, typography rules, GSAP motion skeletons, pre-flight check.
- `redesign-existing-projects`: Audit-first workflow for existing projects without breaking functionality.
- `high-end-visual-design`: Premium agency typography, spacing, shadows, and subtle micro-interactions.
- `minimalist-ui`: Editorial, crisp structure, warm monochrome palette, flat bento grids.
- `industrial-brutalist-ui`: Swiss print typography, rigid grids, analog degradation, raw mechanical contrast.
- `gpt-taste`: Stricter variant: Python-driven randomized variance, strict AIDA, gapless bento, GSAP scroll triggers.
- `full-output-enforcement`: Complete code generation with no truncation or shortcuts.

<!-- changelog-generator:start -->
# Mandatory Changelog Generation on Every Commit

All AI agents (Antigravity, Claude Code, Cursor, Copilot, Roo/Cline, Windsurf) working on this project MUST strictly adhere to this rule whenever preparing or making git commits:

## Mandatory Commit Protocol
1. **Always Update CHANGELOG.md**:
   - Whenever any commit is created, you MUST update `CHANGELOG.md` in the repository root.
   - Stage and include `CHANGELOG.md` in the exact same commit as your code changes.
2. **Structure & Categories**:
   Use the `changelog-generator` skill standard format:
   - `### ✨ New Features` (new functionality, tools, options)
   - `### 🔧 Improvements` (performance, UI enhancements, refactors that benefit users)
   - `### 🐛 Fixes` (bug fixes, error resolutions)
   - `### ⚠️ Breaking Changes` (if any breaking API/config changes)
   - `### 🔒 Security` (security updates or vulnerability fixes)
3. **Tone & Formatting**:
   - Translate technical commits into clear, concise, user-friendly language.
   - Use bullet points with bold feature highlights: `- **Component/Feature**: Clear explanation of what changed and why.`
   - Filter out noise: Do not log trivial typo fixes or internal test refactors unless user-facing.
   - Place updates under `## [Unreleased]` or the corresponding version header.
<!-- changelog-generator:end -->
