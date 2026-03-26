# Quiz App — Agent Instructions

## Project Overview
A quiz web app with Google Auth, score tracking, and Firestore persistence.
Hosted on Netlify as a static SPA.

## Stack (exact versions — do not upgrade without asking)
- React 18 + TypeScript (strict)
- Vite 6 (build tool)
- MUI (Material UI) v6
- React Router v7
- Firebase v11 (Auth + Firestore only — no Storage, no Functions)
- Vitest + React Testing Library

## Project Structure
- `src/pages/` — route-level components (one per route)
- `src/components/` — shared, reusable UI components
- `src/hooks/` — custom hooks (useAuth, useQuiz, useFirestore)
- `src/services/firebase.ts` — Firebase init and exports
- `src/types/` — all TypeScript interfaces and types
- `src/data/` — static JSON quiz files
- `src/theme.ts` — MUI theme config
- `docs/adr/` — architecture decision records (read before making tech decisions)
- `docs/brand.md` — brand guidelines, copy rules, color palette (read before any UI work)
- `tasks/` — todo.md (active task plan) and lessons.md (self-correction log)
- `.agent/commands/` — reusable prompt templates for recurring tasks

## Commands
```bash
# Type check
npm run typecheck

# Lint a single file
npx eslint --fix src/path/to/file.tsx

# Run a single test
npx vitest run src/path/to/file.test.tsx

# Dev server
npm run dev

# Full build (only when explicitly asked)
npm run build
```

## Do
- Read `docs/brand.md` before writing any UI text, labels, CTAs, or copy
- Use functional components with hooks only — no class components
- Use MUI components — never raw HTML `<div>` if an MUI component exists
- Use TypeScript interfaces from `src/types/` for all data shapes
- Use the `useAuth` hook for any auth state access
- Use the `useFirestore` hook for all Firestore read/write operations
- Keep components small and focused (single responsibility)
- Use MUI `sx` prop for inline styles — no CSS files, no inline `style={{}}`
- Prefix Firebase environment variables with `VITE_` in `.env.local`
- Follow the task workflow defined in `WORKFLOW.md`

## Don't
- Do not use class components
- Do not fetch data directly inside JSX — use hooks or services
- Do not hardcode Firebase config values — always use `import.meta.env.VITE_*`
- Do not add new npm packages without asking first
- Do not modify `src/services/firebase.ts` without explicit instruction
- Do not build beyond the defined MVP scope unless asked
- Do not commit directly to `main`

## Git & Release Rules
- Always work on a feature branch, never commit to `main` directly
- Branch naming: `feat/`, `fix/`, `chore/` prefixes (e.g. `feat/quiz-auth-flow`)
- Commit messages in Conventional Commits format: `feat(auth): add Google Sign-In`
- Open a draft PR for any change touching 3+ files
- Never push without passing all verification commands first
- GitHub Actions automatically handles CI on PRs and pushes to `main`.
- We use `release-please` which automatically drafts Release PRs with changelogs. Merging these PRs creates a GitHub Tag and Release.

## Verification Commands (run before marking any task done)
- `npm run typecheck` — zero TypeScript errors required
- `npm run lint` — zero ESLint warnings required
- `npx vitest run` — all tests must be green
- `npm run build` — clean Vite build, no warnings

## Safety & Permissions

### Allowed without asking
- Read and list files
- Lint, typecheck, run single tests
- Create new components or hooks

### Ask first
- Installing npm packages
- Modifying Firebase config or Firestore rules
- Running full build or end-to-end test suites
- Deleting or renaming existing files
- Git commits or pushes

## Patterns to Follow
- Protected routes: wrap in `<ProtectedRoute>` component — redirect to `/login` if unauthenticated
- Auth state: always use `useAuth` hook, never call `firebase.auth()` directly in components
- Forms: use controlled components with `useState`
- Error states: always handle loading and error states explicitly in UI

## When Stuck
- Ask a clarifying question rather than making a large speculative change
- Propose a plan first for any change touching more than 3 files

## Brand & Copy Rules
- See `docs/brand.md` for approved tone, voice, and copy patterns
- Use "drill" as the active verb for quiz interactions (not "start", "take", "begin")
- CTAs must be action-oriented and confidence-reinforcing
- Never use negative framing ("don't fail") — always frame as gain

