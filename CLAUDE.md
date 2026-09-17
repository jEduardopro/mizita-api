# mizita-api

Laravel 13 REST API plus the React front end that consumes it, served from the same origin. The API is the contract: the same endpoints will serve a native client later.

## Instruction layout

This file carries what every session needs: what Mizita is, the domain map, the vocabulary, and the rules that hold no matter which file is open — delegation, security, timezones, clean code.

Everything else is a **path-scoped rule** under `.claude/rules/`, loaded only when Claude reads a matching file:

| Rules | Load when touching |
| --- | --- |
| `.claude/rules/backend/` — architecture, multi-tenant, application, conventions | `app/`, `database/`, `routes/`, `config/`, `tests/` |
| `.claude/rules/frontend/` — architecture, conventions | `resources/js/`, `resources/css/`, `resources/views/` |
| `.claude/rules/database.md` | `database/`, `app/` |
| `.claude/rules/testing.md` | `tests/` |

**Open code with the `Read` tool, not with `cat`, `sed` or `head`.** The `paths:` frontmatter matches the file path the *file tools* touch, so a rule loads on `Read`, `Edit` and `Write` and does not load on a shell command that happens to print the same file. Reading through Bash means working on `app/` or `resources/js/` with the architecture rules absent. Use the shell for what it is for — `grep`, `find`, artisan, `pint` — and open the file itself with `Read`.

Scaffolding a module is the `/new-domain` skill, which holds the `make:domain` reference and the list of what the generator cannot do.

**A rule that must hold regardless of the file being edited belongs in this file, not in a rule file.** A path-scoped rule is absent until something matching is read, and after `/compact` it stays absent until it matches again — which is why the security invariants and the delegation policy are here.

## What Mizita is

Multi-tenant appointment booking. A professional creates a **Business** and registers its **staff**, **services**, **customers** and **working hours**, then runs the whole agenda from one dashboard. A public side lets anyone search across published businesses — by business or by service — and book, either as a guest or with an account.

Two audiences share the platform, and almost every design decision follows from that:

- **Business users** — the owner and their staff. Authenticated, and always operating inside one business.
- **End customers** — the people booking. They may have no account at all, and when they do have one they belong to no business.

Three route stacks follow, and the stack — not the controller — is what guarantees isolation:

| Caller | Middleware | Tenant resolved from |
| --- | --- | --- |
| Business user | `['api', 'auth:sanctum', 'business']` | the caller's staff membership |
| Customer with an account | `['api', 'auth:sanctum']` | **nothing — deliberately cross-tenant** |
| Anonymous | `['api', 'throttle:…']`, plus `business.public` where a slug is present | the business slug in the URL, or nothing |

`BusinessContext` is agnostic about *how* the tenant was chosen, so every use case, repository and global scope works unchanged behind a slug-resolved context. Only the resolution strategy differs. The corollary is a hard rule: **a cross-tenant route must bind no context at all** — binding one would silently narrow a public search to a single tenant.

## Domains

| Domain | Scope | Status | Purpose |
| --- | --- | --- | --- |
| `Businesses` | root | **exists** | The tenant: public profile, timezone, booking policy. Orchestrates onboarding |
| `Customers` | tenant | planned | Per-business client records, optionally linked to an account. A first cut existed and was deleted unfinished, table included — start it again from `make:domain` |
| `Accounts` | root | **exists** | Authentication for both audiences, including Google sign-in |
| `Staff` | tenant | **exists** (owner only) | People who work at the business — **also the access membership** |
| `Industries` | root | **exists** (read only) | Seeded catalog of business categories, keyed in English |
| `Phones` | root | **exists** | Polymorphic phone numbers, one per owner |
| `Services` | tenant | planned | Bookable offerings: duration, buffers, price, eligible staff |
| `Availability` | tenant | planned | Weekly hours and time off for *both* businesses and staff; slot computation |
| `Appointments` | tenant | planned | Booked intervals and their lifecycle |
| `PublicCatalog` | root | planned | Cross-tenant search and the entire anonymous surface |
| `Notifications` | tenant | planned | Confirmations and reminders |

Three structural decisions worth knowing before you touch any of them:

- **A staff row *is* the business membership.** `users` is pure authentication; which business a person may operate is a `staff_members` row. No staff rows means the person is an end customer. This replaced `users.business_id`, which no longer exists. The **role** is not on that row: roles and permissions are `spatie/laravel-permission`, with its teams feature on and the team being the business, so the same account can be an owner in one business and staff in another. `Staff\ValueObjects\StaffRole` stays the domain's vocabulary and names the seeded Spatie roles; nothing above `Infrastructure/` knows the package exists. How the rows are shaped is `.claude/rules/backend/access-model.md`, and it is not the obvious shape.
- **Availability is one domain, not hours inside Businesses plus hours inside Staff.** `schedule_rules` carries `owner_type` + `owner_id`, so both share one entity, one calculator and one test suite. Availability = business hours ∩ staff hours − time off − booked appointments.
- **Every unauthenticated endpoint lives in `PublicCatalog`**, so "what can an anonymous person reach?" has one answer. (`Public` alone cannot be a namespace segment — it is a PHP reserved word.)

## Ubiquitous language

English, everywhere — class names, columns, routes, comments. The non-obvious ones carry the weight:

| Term | Means |
| --- | --- |
| **Business** | The tenant. Every other record belongs to exactly one |
| **Staff member** | A person who works at a business. Also what grants a user access to it, with a role |
| **Service** | Something bookable: a duration, buffers and a price |
| **Schedule rule** | Recurring weekly hours. Its owner is *either* a business or a staff member |
| **Time off** | A dated exception — a holiday, a closure, an absence |
| **Slot** | A bookable start time, already checked against hours, time off and existing appointments |
| **Block** | What a booking actually consumes: buffer before + duration + buffer after |
| **Lead time** | How soon from now a customer may book |
| **Horizon** | How far ahead a customer may book |
| **Granularity** | The step between offered slots — 15 min, 30 min |
| **Guest booking** | A booking with no account, identified by name plus email or phone |
| **Manage token** | The secret in a guest's booking link. A bearer credential — treat it as one |
| **Published** | A business visible in the public catalog |
| **Account** vs **Customer** | One person has one *Account* and one *Customer* record **per business**. The same human booking at two businesses is two Customers joined by one Account |

## Stack

- Laravel 13.31, PHP 8.3+
- PostgreSQL (`mizita_api` locally)
- Sanctum, already wired for both session and token auth: `bootstrap/app.php` calls `statefulApi()`, and `withExceptions` forces JSON rendering for `api/*`. Do not re-add either.
- `spatie/laravel-permission` for roles and permissions, teams on, the team being the business. Confined to `Infrastructure/` and seeded by `AuthorizationSeeder` from `config/authorization.php` — see *Domains* and `.claude/rules/backend/access-model.md`
- Pest for tests, Pint for formatting
- React 19 + Vite 8 + TypeScript in `resources/js` — see `.claude/rules/frontend/`

## Running commands

`php` on `PATH` is PHP 8.5.5 (`/opt/homebrew/opt/php@8.5/bin/php`), so artisan runs either way. Use the absolute path below anyway: the allowlist in `.claude/settings.local.json` is keyed to it, and a bare `php` only earns a permission prompt.

```sh
/opt/homebrew/opt/php/bin/php artisan …
/opt/homebrew/opt/php/bin/php artisan test
/opt/homebrew/opt/php/bin/php vendor/bin/pint --dirty
```

## Tooling

**Delegation is the default.** The main session plans, reads, reviews the result and runs `pint`/`artisan test`; it does not write feature code itself.

| Work | Agent |
| --- | --- |
| `app/`, `database/`, `routes/`, `config/` — production code | **`mizita-backend`** (`.claude/agents/mizita-backend.md`). Owns this architecture, writes production code only; never tests. |
| `tests/` — the Pest suite | **`mizita-tester`** (`.claude/agents/mizita-tester.md`). Writes tests only; never production code. |
| `resources/js`, `resources/css` — UI | **`mizita-frontend`** (`.claude/agents/mizita-frontend.md`). Writes front-end code only; never PHP, never tests. Playwright reaches it as MCP tools from `.mcp.json`. |
| A new module | the **`/new-domain`** skill (`.claude/skills/new-domain/`), a wrapper over `make:domain`. |

### Run everything in parallel that can run in parallel

**Launch as many subagents at once as the work allows**, as several `Agent` calls in a single message. Serialise only on a *real* dependency — one agent cannot start until it has what another produced.

That is why a feature crossing layers still runs backend → tester → frontend: the tester writes against code the backend has just produced, and the front end transcribes a contract that has to exist first. It is not why exploration, impact analysis, an unrelated bug fix or a second domain should wait — those fan out.

Read the dependency, not the habit. A tester updating tests the last change broke and a front-end agent fixing a file the backend never touches are independent, and they go out together.

**The agent type is not the unit of parallelism — the slice of work is.** `mizita-backend`, `mizita-frontend` and `mizita-tester` are each launched as many times at once as the work splits into. Three `mizita-backend` instances writing three domains, four `mizita-tester` instances covering four use cases, two `mizita-frontend` instances on two screens: that is the normal shape of a delegation, not an exception. When handing work over, split it as far as it will go and launch all the pieces in one message.

Split along files, and the one hard constraint follows from it: **two agents must never write the same file.** They cannot see each other's edits, so the second one to save wins and the first one's work disappears with no error anywhere. Before fanning out, name the files each instance owns; when a file would be shared — a service provider, a config catalogue, a locale bundle, a migration everyone appends to — either give it to exactly one instance or keep it for the main session afterwards. A slice that cannot be drawn without overlap is a slice that stays whole.

Each instance gets the full context it needs on its own: they do not talk to each other, and an instruction to "coordinate with the other agent" is never satisfiable.

The same rule governs tool calls: independent `Read`, `Grep`, `Glob` and `Bash` calls go in one message, never chained one per turn.

### When the main session may edit directly

Only changes that are both small and unverifiable by tests:

- typos, formatting, a local variable rename, deleting a comment or a stale docblock
- `CLAUDE.md`, `README`, `.env.example`, editor and CI config
- a one-line change with no behavioural effect

**Override — behaviour beats size.** A change to `app/` that alters behaviour at all is delegated no matter how few lines it is, because it has to be covered or re-verified by the suite: `mizita-backend` makes the change, `mizita-tester` writes or updates the test. A green existing suite is not a reason to skip this.

## Plan mode: ask, never assume

**In plan mode nothing is assumed.** The order is fixed: explore the code read-only until the ground is understood, then use `AskUserQuestion` for everything that admits more than one reasonable reading — with concrete options grounded in what was just read, not generic ones.

Nothing gets implemented until the request is understood with at least 95% confidence. Below that bar the answer is another question, never a guess dressed as a default. A single ambiguity does not block the rest: do everything it does not touch, and ask about the part it does.

Two things are always worth a question rather than a decision: **anything that changes the behaviour of a screen or an endpoint that already exists**, however small it looks, and **anything whose consequences reach beyond the file being edited** — a relaxed invariant, a widened type, a new column on a shared table.

## Identity: uuid outward, int inward

Two identities per record, and which one you are holding is never a matter of taste:

- **Every table carries both** — `id` bigint auto-increment as the primary key, and a unique `uuid` column.
- **The uuid is the only identity that leaves the application.** Entities, DTOs, Resources, events, URLs, route keys, query keys, TypeScript types. An `id` on the wire is always a uuid string, never `1`, `2`, `100` — sequential keys leak row counts and invite enumeration.
- **Foreign keys reference the int**, always: `foreignId('account_id')->constrained('users')`, `business_id` → `businesses.id`. Never a uuid FK.
- **The repository adapter is the only place allowed to hold both halves.** An entity carries a neighbour's uuid, the adapter resolves it to the int going in and reads it back off the eager-loaded relation coming out. That translation never appears above `Infrastructure/`.

The consequence to internalise: a use case, a controller, a Resource or a test that mentions an int id is wrong by construction, and a `number` typed as an id on the front end is the same bug seen from the other end. `.claude/rules/database.md` carries the mechanics.

## Time and timezones

A booking product lives or dies on this, so it is a rule, not a preference.

- **Every business carries an IANA timezone** (`Europe/Madrid`), and it is the only source of local time. Never `config('app.timezone')`, never a fixed offset, never `+02:00` in a string.
- **Store instants as `timestampTz`, in UTC.** PHP works in UTC throughout; `DATE_ATOM` on the wire, which the generated Resources already do.
- **Recurring hours are local-time facts** ("Mondays 09:00–17:00"); appointments are absolute instants. Convert **local → UTC per date**, never by adding a constant. Across a DST boundary the offset changes and any shortcut is wrong.
- Test both DST days explicitly with a real zone. On a spring-forward day 02:00–03:00 local does not exist; on a fall-back day it happens twice.
- `Clock` is injected, always. A use case never calls `now()`, and a pure domain service takes `now` as a **parameter** so it can be tested without a clock at all.

## Security invariants

Each of these is a rule because getting it wrong is an incident, not a bug.

- **Guest booking history is linked to an account only after the email is verified.** Otherwise registering with a guessed address exposes that person's appointments across every business on the platform.
- **A public Resource is a separate class**, never a tenant Resource reused. No public endpoint returns a Customer, or an appointment's customer fields.
- **Appointment overlap is prevented by the database**, with a Postgres exclusion constraint over `(staff_id, tstzrange(starts_at, ends_at))`. Application-level checks cannot close the race between two simultaneous bookings. The repository catches SQLSTATE `23P01` and the controller renders **409**.
- **A cross-tenant route binds no business context.** `/api/me/appointments` and public search filter explicitly and deliberately run outside the global scope, so a forgotten `where` there leaks other people's data. Give each one a dedicated read adapter and a test asserting another account's rows are absent.
- **A missing business on a public slug is 404, not 403.** A 403 confirms the slug exists.
- **The manage token is a bearer credential**: 256 bits of randomness, unique index, and an expiry.

## Clean code practices and SOLID principles

Requirements on both stacks, PHP and TypeScript alike. The layered architecture in `.claude/rules/backend/` exists to make them achievable — it does not grant them, so every change is held to them.

The five SOLID principles, in this project's terms:

| Principle | Here it means |
| --- | --- |
| **Single Responsibility** | A use case orchestrates, an entity holds the rules, a mapper translates, a repository persists, a controller adapts HTTP. On the front end a component either fetches, arranges, or renders — never all three. Needing the word "and" to describe a class means it is two classes |
| **Open/Closed** | New behaviour is a new implementation of a port, a new use case, or a new variant — never a growing `match` on a string, and never a new boolean flag on an existing entry point |
| **Liskov Substitution** | Every adapter honours its port's full contract — same return types, same nullability, same exceptions. Every wrapper over a `components/ui/*` primitive stays substitutable for it |
| **Interface Segregation** | Narrow ports and narrow props: the consumer declares only what it calls. Same rule as *Crossing domains* in `.claude/rules/backend/architecture.md`, applied inside a domain too |
| **Dependency Inversion** | Use cases depend on `Contracts/`; components depend on hooks. Concrete classes, Eloquent and URLs live at the edges |

Clean code practices, the checkable floor:

- Names state intent — no `$data`, `$info`, `$temp`, no abbreviations. A method name should let you predict its body.
- Guard clauses and early returns over nesting; no `else` after a `return`.
- No magic numbers or strings — a named constant or a backed enum.
- No boolean flag parameters: `publish()` / `unpublish()`, never `setPublished(bool)`.
- A rule attached to a primitive — email, slug, money, timezone, duration — is a value object, not validation scattered across use cases. A rule about one payload's own shape lives in that payload's `validate()`; a rule two payloads share is a value object they both build.
- **Tell, don't ask**: `$appointment->cancel($now)`, never read an entity's state at the call site and decide on its behalf.
- DRY with judgment — duplicate twice before abstracting, and **never DRY across domains**: shared code there is coupling, and the answer is a port.
- **We write no comments.** Not "few", not "only the good ones" — none. A name, a guard clause or a smaller method says it better and cannot go stale. If a comment feels necessary to explain *what* something does, rename it or split it. Delete dead code instead of commenting it out.
  Four things are not our comments and stay:
  - **Code we did not write.** Published vendor config — `config/app.php`, `config/fortify.php`, `config/permission.php`, `config/sanctum.php` and the rest of the Laravel and Spatie skeleton — plus the Pest and Laravel scaffold in `tests/`. Only `config/authorization.php`, `config/localization.php` and `config/preferences.php` are ours.
  - **Annotations a tool reads**, which are contract and not prose: `@var`, `@template`, `@extends`, `@use`, `@property-read`, `@param`/`@return` carrying generics or an array shape, `@throws`, and on the front end `@ts-expect-error` and `eslint-disable`. A docblock that holds only tags survives whole; one that mixes prose with tags keeps the tags and loses the prose. A `@param string $name` that only restates the signature is prose.
  - **A comment recording a bug, an issue or a deliberate oddity** — the `make:domain` note in `bootstrap/providers.php`, the rules kept outside `@layer` in `resources/css/app.css`. One or two lines, and only where the next reader would otherwise "fix" it back. This is the narrow exception, not a re-entry for rationale.
  - **A comment the user asked for.**

The full per-stack checklists and the self-check questions live in `.claude/agents/mizita-backend.md` and `.claude/agents/mizita-frontend.md`.

