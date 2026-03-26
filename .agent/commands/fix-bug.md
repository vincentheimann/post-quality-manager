# Bug Fix Checklist

1. Read the full bug report or error log before doing anything
2. Identify the root cause — do not patch symptoms
3. Write plan to `tasks/todo.md` describing root cause and proposed fix
4. Create a fix branch: `fix/[short-description]`
5. Implement the fix touching only the necessary files
6. Add or update a test that would have caught this bug
7. Run all verification commands: typecheck → lint → vitest → build
8. Provide a one-paragraph explanation of what caused the bug and how it was fixed
9. Commit using Conventional Commits format: `fix(scope): description`
10. Update `tasks/lessons.md` with the pattern to prevent recurrence
