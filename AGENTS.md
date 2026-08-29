# Samtli Development Rules

## Project Purpose

Samtli is a server-rendered PHP community platform for interest-based groups, discussions and role-based membership management. It is both a graded course assignment and a portfolio project.

Do not implement broad portfolio features before the assignment requirements are complete.

## Assignment Strategy

The first milestone is full VG completion. Implement all G and VG requirements securely before expanding the product.

Functional behavior follows `docs/ASSIGNMENT.md`. The roadmap follows `docs/ROADMAP.md`.

## Architecture

- Controllers handle HTTP/application requests and coordinate operations.
- Services contain business/use-case logic.
- Repositories contain PDO persistence and query logic.
- Security centralizes authentication, authorization and CSRF behavior.
- Templates are server-rendered PHP presentation files.
- Database migrations and seeds live under `database/`.

Keep responsibilities clear without adding unnecessary enterprise abstraction.

## Frontend Constraints

- Use server-rendered PHP, HTML5, project-owned CSS and small amounts of vanilla JavaScript only.
- Do not use React, Vue, Svelte, Angular, Alpine or another frontend framework.
- Do not build a SPA.
- Do not copy generated Stitch Tailwind markup into production runtime.
- Shared UI should be implemented as reusable PHP templates/components when it becomes necessary.

## Design Source Of Truth

`docs/design-reference/` is the authoritative visual source of truth for Samtli.

Before implementing or modifying any UI:

1. inspect the relevant Stitch screenshot;
2. inspect related screens that reuse the same component;
3. inspect `docs/design-reference/DESIGN.md`;
4. inspect `docs/design-reference/COMPONENT_INVENTORY.md`;
5. reuse existing shared components before creating new ones.

Do not invent a different visual design when Stitch already defines the pattern.

When multiple Stitch screens show slightly different versions of the same shared component, consolidate them into one canonical reusable component rather than reproducing inconsistent markup.

Visual consistency across the final application is more important than reproducing accidental inconsistencies from individual generated Stitch screens.

Assignment functionality overrides Stitch when they conflict. Stitch controls visual intent. The assignment controls functional behavior.

## Binary Design Assets

The original Stitch archive in `docs/design-reference/stitch_samtli_community_platform.zip` is the canonical source for imported design assets. Stitch screenshots and other binary assets must be copied or extracted with binary-safe filesystem operations, never reconstructed through text-only file-writing mechanisms. When importing or updating screenshots, validate destination files against the source using PNG signatures and hash comparison where appropriate.

## Shared Component Consistency

Headers, navigation, buttons, form controls, badges, avatars, group cards, discussion rows, reply composers, admin tabs, account menus and state messages must use one coherent Samtli design system.

Legitimate variants are allowed for authentication state, active navigation, administrator permissions, responsive layouts and compact contexts.

## Security

- Use `password_hash()` for passwords.
- Use `password_verify()` for authentication.
- Use PDO prepared statements.
- Never interpolate raw user input into SQL.
- Escape untrusted output appropriately.
- Use CSRF protection for state-changing browser requests.
- Validate input server-side.
- Enforce authorization server-side.
- Hiding a button is not authorization.
- Check group data access against the authenticated user's membership.
- Verify administrator role server-side for administrator operations.
- Never trust client-provided role or member information.
- Generate invitation tokens securely when the invitation feature is implemented.
- Do not store invitation tokens in plaintext.
- Never commit secrets.
- Changing a URL or ID must never bypass authorization.

## Database Rules

Use MariaDB/MySQL-compatible SQL and PDO. Keep database state out of Git. Commit migrations and seed definitions only, not local database contents or dumps.

## Docker And Coolify

- Docker is the standard runtime.
- Local development must work through Docker Compose.
- Production deployment target is Coolify on the homelab.
- The application container should generally listen on port `80`.
- The fixed production host port is `38515`.
- The planned public production URL is `https://samtli.hampusandersson.dev`.
- Production configuration belongs in environment variables injected by Coolify.
- Database state must be persistent.
- Database credentials must never be committed.
- Deployment configuration must not bypass local reproducibility.

## Testing And Postman

Run focused PHP checks before committing. Add automated tests as application logic appears. Use Postman later to verify API-relevant flows required by the course.

## Branches

Use `feature/<area>/<change>` for features, for example:

- `feature/home/layout`
- `feature/auth/registration`
- `feature/groups/create`
- `feature/discussions/replies`
- `feature/admin/member-roles`
- `feature/invitations/hot-link`

Use `fix/<area>/<change>` for fixes.

## Commits

Commits must be small, logical, descriptive, chronological, based on real work and understandable without reading the entire diff.

Acceptable examples:

- `feat: add user registration`
- `feat: add group creation`
- `security: enforce group membership access`
- `fix: prevent invite reuse`
- `style: add responsive discussion layout`
- `docs: document local Docker setup`

Never create fake progress commits, empty history-padding commits, backdated commits, tool-named commits or giant project-wide commits.

## Merging

Prefer preserving individual commits from feature branches. Do not squash a multi-commit feature branch into one commit if doing so would destroy the development history required by the assignment.

Do not rewrite already-pushed history without a genuine technical reason.

## Definition Of Done

A change is done when it is scoped, understandable, secure for its surface area, verified by relevant commands, documented where needed and committed with a normal product or technical message.
