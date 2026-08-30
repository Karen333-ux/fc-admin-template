# CLAUDE.md — Future Code Admin Template

Guidance for Claude Code when working in this repository.

## Project

A production-ready Laravel + Filament admin template for Future Code (كود المستقبل).
It is a **foundation reused across projects** — every decision here is multiplied by
every project built on it. Prefer configurability over hardcoding, always.

Full documentation is in Arabic under `docs/`. Read `docs/01-architecture-ddd.md`
and `docs/18-conventions.md` before writing any code.

## Stack (pinned — do not change without discussion)

- PHP 8.4 · Laravel 13 · Filament 5.7+ · Livewire 4 · Tailwind CSS 4.1+
- PostgreSQL 18 · Redis 8 · Node 22 LTS
- Multi-tenant: single database, `tenant_id` row-level isolation

## Non-negotiable rules

1. **No hardcoding.** Colors, permissions, disks, settings, and copy all come from
   config, database, or translation files.
2. **All authorization goes through Laravel Policies. No exceptions.**
   `hasPermissionTo()` / `hasRole()` / `hasAnyRole()` may appear in exactly two places:
   the base `Src\Support\Domain\Authorization\Policy` class, and the Gate definitions in
   `AuthorizationServiceProvider`. Everywhere else calls `can($ability, $model)`.
   - `$user->can('publish.announcements')` ❌ — that is a permission *name*
   - `$user->can('publish', $announcement)` ✅ — that is an *ability*, routed to the policy
   A policy method holds **the permission check AND the business rules together**, returns
   `Illuminate\Auth\Access\Response` (never `bool`) so the denial reason reaches the user,
   and is testable without booting the UI. See `docs/19-policies.md`.
3. **Every UI element — including a single button — is authorization-gated**, and the
   endpoint behind it is independently authorized. Hiding is UX; the policy is security.
4. **Every tenant-owned model uses `BelongsToTenant`.** A query without tenant context
   throws rather than returning all rows.
5. **Zero literal strings in code.** Everything goes through `__()`.
6. **Zero directional CSS.** `start`/`end`, never `left`/`right`.
7. **Business logic lives in Application Actions**, never in Filament Resources.
8. **`env()` only inside `config/`** — it returns null once config is cached.

## Architecture

```
src/
├── Support/                  # shared across contexts
└── Contexts/<Name>/
    ├── Domain/               # models, enums, value objects, events, repo interfaces
    ├── Application/          # actions (use cases), DTOs, queries
    ├── Infrastructure/       # repo implementations, notifications, listeners, policies
    ├── Presentation/         # Filament resources, pages, widgets
    ├── Database/ Lang/ Routes/ Tests/
    └── <Name>ServiceProvider.php
```

Dependency direction: `Presentation → Application → Domain`.
`Domain` imports nothing from Filament, HTTP, or Livewire.
Contexts communicate via **domain events** or a single public Application class —
never by importing each other's models.

## Adding a feature

Follow `docs/16-adding-a-feature.md` exactly. The order matters:

1. Permissions in `config/authorization.php` + translations + `php artisan authorization:sync`
2. Migration (`tenant_id`, composite index, `softDeletes`, `json` for translatable)
3. Domain: Enum → Model (with `BelongsToTenant`) → Event
4. Application: DTO → Action (with `Gate::authorize()` as the last barrier)
5. Infrastructure: **Policy (permission + business rules)** → Listener → Notification
6. Presentation: Filament Resource — `->authorize()` only, never a permission string
7. Translations `ar` + `en`, including the policy's denial messages
8. Tests: role matrix + one test per business rule + tenant isolation + super-admin invariants
9. Verify: `composer test && composer lint`, screenshots, Telescope query count

## Commands

```bash
composer test                    # Pest
composer lint                    # Pint --test + PHPStan
composer fix                     # Pint
php artisan authorization:sync   # sync permissions from config
php artisan filament:optimize-clear
```

## Before finishing any task

- [ ] `composer test` passes
- [ ] `composer lint` passes
- [ ] No hex colors outside the theme file
- [ ] No literal user-facing strings
- [ ] No `pl-`/`pr-`/`ml-`/`mr-`/`text-left`/`text-right`
- [ ] No `hasPermissionTo()` / `hasRole()` outside the Policy base class and Gate definitions
- [ ] No `can('action.resource')` permission strings — use `can('ability', $model)`
- [ ] No `->skipAuthorization()` anywhere
- [ ] Every new action uses `->authorize()`; bulk actions use `->authorizeIndividualRecords()`
- [ ] Every policy method returns `Response`, not `bool`
- [ ] Business rules live in the policy, not in the Resource
- [ ] Sensitive form fields block persistence (`->saved(false)`), not just `->disabled()`
- [ ] New model with `tenant_id` uses `BelongsToTenant`
- [ ] New permission has `ar` and `en` labels
- [ ] Query count for any table page ≤ 10

## Things that are easy to get wrong here

- `shouldRegisterNavigation()` hides the link but does **not** block the URL —
  always pair it with a policy or `canAccess()`.
- `->disabled()` alone is not security; the value still arrives in the request.
- `Gate::before` returning `true` **bypasses the policy entirely, including business
  rules** — so a blanket super-admin bypass lets them delete themselves, publish an
  incomplete record, or impersonate another super-admin. It must consult the policy's
  `invariants()` and return `null` for those abilities. See `docs/19-policies.md` §5.
- `Gate::before` must return `null` (not `false`) for non-super-admins.
- Models live outside `App\Models`, so policy auto-discovery needs
  `Gate::guessPolicyNamesUsing()` mapping `\Domain\Models\` → `\Infrastructure\Policies\`.
- `->authorize('x')` on a Filament action auto-prepends the record as the policy argument —
  do not pass it yourself.
- `->authorize()` on a bulk action is one blanket check; `->authorizeIndividualRecords()`
  runs the policy per selected record and drops the ones that fail.
- `dehydrated(false)` still works, but `saved(false)` is the current documented idiom in
  v4/v5 — verify against the installed version and write a test proving the value is not persisted.
- spatie/permission reads its cache key during `boot()`, before tenant middleware —
  call `setPermissionsTeamId()` + `forgetCachedPermissions()` after switching tenant.
- Queued jobs carry no tenant context — pass `tenantId` explicitly.
- Filament v4/v5 requires explicit `->columnSpanFull()` on `Section`/`Grid`/`Fieldset`.
- Row actions are `->recordActions()`, toolbar/bulk are `->toolbarActions()` (renamed from v3).
- Dark-mode status colors (`#4ADE80`, `#FBBF24`, `#F87171`) fail contrast on white —
  never use them in light mode.

## Security

`docs/20-security.md` is a set of acceptance conditions, not advice. The three highest-risk
areas in this codebase, in order:

1. **Cross-tenant leakage** — a query that forgot its scope. Silent, so it needs the
   arch test that walks every model automatically.
2. **Privilege escalation** — a hidden button whose endpoint is open, or a `disabled()`
   field whose value still saves.
3. **Stored XSS from the rich editor** — `RichEditor` stores HTML; rendering it raw runs it.
   Sanitize on save *and* on output, with a tag allow-list.

Never `$guarded = []`. Never `{!! !!}` on unsanitized content. Never `whereRaw`/`orderByRaw`
with user input without a column allow-list. Never an SVG upload into a public collection.

## Verification before claiming a package version

Package versions in `README.md` were researched in August 2026. Before installing,
confirm with `composer show <package> --available` that the version exists and that
its Filament constraint includes `^5.0`. Do not downgrade Filament for a plugin.
