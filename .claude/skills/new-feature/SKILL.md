---
name: new-feature
description: New feature workflow for the Post Quality Manager plugin — plan in tasks/todo.md, validate with user, feature branch, KISS implementation, manual testing. Use when the user requests a new feature or enhancement.
---

# New Feature Checklist

1. Write plan to `tasks/todo.md` with checkable steps before touching any code
2. Check in with the user to validate the plan
3. Create a feature branch: `feat/[short-description]`
4. Build the feature following all WP plugin stack rules in `AGENTS.md` (KISS principle)
5. Read `docs/brand.md` before writing any admin UI text, labels, or copy
6. Test manually in the WordPress Admin or via WP REST API
7. Provide a high-level summary of changes before committing
8. Commit using Conventional Commits format
9. Mark todo items complete and add an entry to `tasks/lessons.md` if any correction was made
