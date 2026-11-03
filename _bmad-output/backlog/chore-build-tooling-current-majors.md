---
id: 103
type: story
title: "The admin app builds with the current major of @wordpress/scripts"
parent: none
covers: [O9]
after: []
assignee: ""
refined: false
hitl: true
risk: medium
---

# The admin app builds with the current major of @wordpress/scripts

## Description

The React pages build with `@wordpress/scripts` 30.27, while its current major is 35. TypeScript in the dev tooling is 5.3 against 7.0. Each major can change the build output, the lint rules and the Jest setup the unit tests use. This story moves the build tooling to current majors and keeps the built pages the same.

## Acceptance Criteria

1. **The tooling is current**
   **Given** `package.json` and the lockfile
   **When** they are read
   **Then** `@wordpress/scripts` is on its current major, and TypeScript on the version that major supports
2. **The gates still pass**
   **Given** the updated tooling
   **When** `npm run lint:js`, `npm run test:unit` and `npm run build` run, locally and in CI
   **Then** each passes, with any lint-rule change resolved in code, not by disabling the rule
3. **The pages behave the same**
   **Given** the Dashboard, Post Reviews and the setup dialog built with the new tooling
   **When** they are opened on the showcase site (Free and Pro builds)
   **Then** they load and work as before, with no console error, and Plugin Check on the built plugin is unchanged

## Boundaries

- Must not change: the WordPress floor (6.6) the built pages support; the plugin's PHP.
- Out of scope: new React features.

## References

- decision — docs/adr/0017-life-cycle-management-by-a-recurring-check.md (the build rule)
- decision — docs/adr/0005-wordpress-components-for-app-screens.md
- record — memory/lifecycle.md

## Notes

- Fact (`/lcm`, 2026-11-03, `npm outdated`): `@wordpress/scripts` 30.27.0 → 35.0.0; `typescript` 5.3.3 → 7.0.2.
- Open question: whether the new major still supports the 6.6 floor's script dependencies; read its changelog first.
- hitl: the owner looks at the pages on the showcase after the build, or the agent's walk captures them.
