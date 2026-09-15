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

