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
