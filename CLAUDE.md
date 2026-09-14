# mizita-api

Laravel 13 REST API plus the React front end that consumes it, served from the same origin. The API is the contract: the same endpoints will serve a native client later.

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
| `Customers` | tenant | **exists** (create only) | Per-business client records, optionally linked to an account |
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

- **A staff row *is* the business membership.** `users` is pure authentication; which business a person may operate is a `staff_members` row. No staff rows means the person is an end customer. This replaced `users.business_id`, which no longer exists. The **role** is not on that row: roles and permissions are `spatie/laravel-permission`, with its teams feature on and the team being the business, so the same account can be an owner in one business and staff in another. `Staff\ValueObjects\StaffRole` stays the domain's vocabulary and names the seeded Spatie roles; nothing above `Infrastructure/` knows the package exists. How the rows are shaped is the *Access model* below, and it is not the obvious shape.
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

- Laravel 13.31, PHP 8.4+
- PostgreSQL (`mizita_api` locally)
- Sanctum, already wired for both session and token auth: `bootstrap/app.php` calls `statefulApi()`, and `withExceptions` forces JSON rendering for `api/*`. Do not re-add either.
- `spatie/laravel-permission` for roles and permissions, teams on, the team being the business. Confined to `Infrastructure/` and seeded by `AuthorizationSeeder` from `config/authorization.php` — see *Domains* and *Access model*
- Pest for tests, Pint for formatting
- React 19 + Vite 8 + TypeScript in `resources/js` — see [Frontend](#frontend)

## Running commands

`php` on `PATH` is PHP 8.5.5 (`/opt/homebrew/opt/php@8.5/bin/php`), so artisan runs either way. Use the absolute path below anyway: the allowlist in `.claude/settings.local.json` is keyed to it, and a bare `php` only earns a permission prompt.

```sh
/opt/homebrew/opt/php/bin/php artisan …
/opt/homebrew/opt/php/bin/php artisan test
/opt/homebrew/opt/php/bin/php vendor/bin/pint --dirty
```

## Architecture: layered DDD per domain

One folder per domain under `app/Domains/<Domain>/`, namespaced after the path. The existing `App\` PSR-4 root covers it, so `composer.json` never changes.

```
app/Domains/Customers/
├── Contracts/                 CustomerRepository.php          ← ports
├── Entities/                  Customer.php                    ← pure PHP, business rules
├── ValueObjects/
├── Services/                  pure, stateless domain services
├── Events/                    CustomerCreated.php
├── Exceptions/
├── Application/
│   ├── UseCases/              CreateCustomer.php
│   ├── Dtos/                  CreateCustomerInput.php, CustomerData.php
│   ├── Jobs/  Commands/  Listeners/
├── Infrastructure/
│   ├── Eloquent/              Models/CustomerModel.php
│   │                          Mappers/CustomerMapper.php
│   │                          EloquentCustomerRepository.php
│   ├── Gateways/              adapters for other domains' ports
│   └── Http/                  Controllers/ Requests/ Resources/ Policies/ routes.php
└── CustomersServiceProvider.php
```

Create a folder only when the first file needs it. Never scaffold empty directories.

`Services/` holds logic that is genuinely domain logic but belongs to no single entity — the availability slot calculator is the motivating case. Same import rules as `Entities/`: plain PHP, no clock, no repository, no container. A domain service that needs to *fetch* something is a use case in the wrong folder.

### Layer dependency rules

| Layer | May import | Must never import |
| --- | --- | --- |
| Domain — `Contracts/ Entities/ ValueObjects/ Services/ Events/ Exceptions/` | plain PHP, `DateTimeImmutable`, same-domain classes, `app/Shared/ValueObjects/` | anything `Illuminate\*`, Eloquent, Carbon, `Infrastructure\*`, another domain |
| `Application/` | its own domain layer, `app/Shared/Contracts/`, framework *interfaces* only (`Illuminate\Contracts\Events\Dispatcher`, `ShouldQueue`, `Illuminate\Console\Command`) | Eloquent, facades, `Illuminate\Http\*`, `Infrastructure\*` |
| `Infrastructure/` | everything | — |

**Dependencies point inward only.** Wiring happens in the domain's service provider.

`Jobs/`, `Commands/` and `Listeners/` sit in `Application/` even though they extend framework base classes — they are the only exception there, and must stay thin wrappers around a use case.

The domain layer uses `DateTimeImmutable`, never `Carbon`.

### Crossing domains

A domain never imports another domain. When a use case needs a neighbour's data, **the consumer declares the port**:

1. A narrow interface in the *consuming* domain's `Contracts/`, returning that domain's own small DTO or value object — `Appointments\Contracts\ServiceCatalog::describe(string $serviceId): ServiceSnapshot`.
2. An adapter in the consuming domain's `Infrastructure/Gateways/` that implements it by calling the other domain's repository or use case — `Appointments\Infrastructure\Gateways\ServicesServiceCatalog`.
3. Bound in the consuming domain's service provider.

The consumer owns the shape of what it needs, so the neighbour's DTOs never leak across the boundary and the use case stays constructible with a mock. For the write side and anything asynchronous, use domain events plus `Application/Listeners/` instead.

This is the rule most likely to be broken by accident, because a direct import compiles and passes tests. It still couples two domains permanently.

## Identity: uuid public, int internal

Every table has both:

```
id     bigint PK auto-increment   ← internal joins and indexes, never leaves Infrastructure
uuid   uuid unique                ← the domain identity, exposed by the API
```

- The entity's `id` property holds the **uuid**, generated in memory by `IdGenerator` before the save — that is what keeps use cases runnable without a database.
- Repositories look up and upsert by the `uuid` column, never by the int.
- The int primary key never appears in an entity, DTO, Resource or event payload.
- Models use `HasUuids` with `uniqueIds()` overridden to `['uuid']`, which leaves the primary key auto-incrementing, and `getRouteKeyName()` returning `'uuid'`.

**Foreign keys reference the int primary key**, never the uuid: `foreignId('account_id')->constrained('users')`. Narrower indexes, cheaper joins, and the ordinary relational shape.

The two rules meet in the repository adapter, which is the only place that may hold both halves of an identity. An entity carries a neighbour's **uuid** (`$identity->accountId`), so the adapter resolves it to the int on the way in and reads the uuid back on the way out — eager-loading the relation rather than issuing a query per row. That translation never leaks above `Infrastructure/`.

## Multi-tenant: everything belongs to a Business

The system is multi-business. `Businesses` is the **root** domain — it is not scoped to itself. Every other domain is tenant-scoped by default.

Tenant-scoped tables carry `business_id` as a foreign key onto **`businesses.id`**, the int primary key, per the identity rule above. The entity still carries the business **uuid** in `public readonly string $businessId`, so the repository adapter translates between the two and nothing above `Infrastructure/` ever sees the int.

**`customers.business_id` still holds a uuid** — it predates this rule and has not been migrated yet. Do not copy it; new tables use the int FK. (`users.business_id` was the other offender and is gone.)

Tenancy is explicit in the domain, not magic:

- The entity carries `public readonly string $businessId`.
- The use case injects `App\Shared\Contracts\BusinessContext` and passes `businessId: $this->business->currentBusinessId()` into `Entity::create()`. A unit test injects a fake context and can assert isolation with no database.
- `App\Http\Middleware\SetBusinessContext` (alias `business`, registered in `bootstrap/app.php`) resolves the business from the caller's **staff membership** through `App\Shared\Contracts\BusinessMembership`, and **aborts 403** when the caller has none. It never binds a null context. A caller with more than one membership picks with an `X-Business` header carrying the business uuid, validated against their memberships; with no header it gets the owner membership, else the oldest. The middleware also calls `setPermissionsTeamId()` with the business's int key — without it every `can()` and `hasRole()` check silently matches nothing.
- `App\Shared\Infrastructure\Concerns\BelongsToBusiness` adds a global scope and a `creating` hook to the model. It is a safety net — the repository already writes `business_id` from the entity. When no context is bound (console, migrations, seeders) the scope is skipped, which is why HTTP isolation is guaranteed by the route middleware, not by the trait.

**The trait is incompatible with an int `business_id`**: it writes and filters the column with the context *uuid*, so on a new tenant table it would compare a bigint to a uuid and Postgres would raise `22P02`. Do not add it to a table following the int-FK rule until a sibling trait exists that memoises uuid→int per request. `staff_members` also omits it for a second reason: it is the table that *resolves* the tenant, so it must be queryable before any context is bound.

Write new code against `BusinessContext`. `User::business()` is gone.

Tenant-scoped domains expose routes under `['api', 'auth:sanctum', 'business']`; root domains get `['api']`.

Generate a root domain with `--root`:

```sh
artisan make:domain Businesses --root --field="name:string" --field="slug:string:unique"
```

## Access model

`config/authorization.php` is the whole access model in one file: every permission with its `scope` and `module`, and every role with the permissions it holds. `AuthorizationSeeder` reads it and syncs. Human labels never touch the database — `permissions.description` and `roles.description` store a **translation key**, resolved from `lang/{en,es}/{permissions,roles}.php`, and `slug` is the kebab of the name.

Two shapes of role, and the difference is the thing to understand before touching any of it:

| | `owner` | `staff`, and every editable role after it |
| --- | --- | --- |
| Rows | **one**, global, `business_id` NULL, `id` pinned to `SeededStaffRole::OWNER_ID` | **one per business**, `business_id` set |
| Created by | the seeder | `BusinessRoleTemplates::cloneFor()`, inside `OnboardBusiness`'s transaction |
| Editable by the owner | never | yes — that is the entire point |

**The template is the config file, not a row.** There must never be a role row with `business_id IS NULL` named `staff`. `Role::findByParam()` matches `business_id IS NULL OR business_id = <team>` and returns `first()` with **no `ORDER BY`**, so a global template and a per-business clone sharing a name are ambiguous: `syncRoles('staff')` would attach an undefined one of the two, and if it picked the template every permission the owner edited would be silently inert. Keeping the template out of the table is what makes exactly one row match under a team — and why `StaffRoleAssignments` needs no special resolution logic. For the same reason `Role::create()` is banned on the clone path (it runs `findByParam` and can throw `RoleAlreadyExists` against the wrong row); use `Role::query()->firstOrCreate()`.

`owner` is global **and must stay that way**. `ownsAnyBusiness()` matches `roles.name = 'owner'` across every team, and `model_has_roles_single_owner_unique` names `role_id = 1` literally in its index predicate. Clone `owner` and an account can quietly come to own two businesses.

Consequences worth knowing before they bite:

- **The seeder syncs templates only and never a clone.** Re-seeding must not restore a permission an owner deliberately removed — that is a security change disguised as a deploy step.
- **A business created before a new permission is added does not receive it from the seeder.** Reaching existing businesses is an explicit backfill, not reference data.
- **`syncRoleIdSequence()` is runtime-critical, not tidiness.** Clones take sequence ids inside the onboarding transaction; a lagging sequence makes the first clone collide with `roles.id = 1` and breaks signup.
- **Spatie's permission cache is one blob holding every role row and every permission→role edge**, loaded on the first permission check of each request, so it grows linearly with the number of businesses. Revisit past a few thousand roles — and never shrink it by stripping `scope` from the cached columns, since a `Permission` read back with `scope === null` defeats the invariant that a business owner can never hold a platform permission.

The rules an account is held to: **owner of at most one business, staff at as many as it likes, exactly one role per business** — the last enforced by `model_has_roles_single_role_per_business`.

Planned and deliberately absent: the **platform plane** (roles assigned with a NULL team, for operating the system itself). The schema is ready for it — `scope` already distinguishes `platform` from `business` — but nothing is seeded, no middleware exists and no endpoint uses it. Landing it needs one migration: `model_has_roles.business_id` is `NOT NULL` and part of the composite primary key, and Postgres here is 14, so `UNIQUE NULLS NOT DISTINCT` is unavailable and the replacement is an expression index over `coalesce(business_id, 0)`. A `RequirePermission` middleware aliased `permission`, denying with `abort_if(..., 403, ...)` to match `SetBusinessContext` and never naming the permission in the message, is the intended shape when a route first needs one.

## Soft deletes

Every Eloquent model uses `SoftDeletes` and every migration ends with `$table->softDeletes()`. The repository port exposes `delete(string $id): void`, so removal is reachable from the domain instead of being dead infrastructure.

A soft delete is the *record* lifecycle. An `active` style flag is a *business* state that lives on the entity — they are different things, and a domain may need both.

## Entities vs Eloquent models

A domain entity is not an Eloquent model.

- `Entities/Customer.php` — pure PHP, zero framework imports. Private constructor, `create()` / `restore()` named constructors, behavior methods instead of setters, invariants that throw domain exceptions.
- `Infrastructure/Eloquent/Models/CustomerModel.php` — the `Model` suffix is mandatory.
- `Infrastructure/Eloquent/Mappers/CustomerMapper.php` translates both ways. The repository adapter is the only class touching both sides.
- Entities never leave the application layer: use cases return DTOs.

## Use cases

`final class`, one public `handle(InputDto): OutputDto`. Constructor takes only interfaces. Inside a use case, no Eloquent, no facades, no `now()`, `config()`, `app()`, `Str::uuid()`, no `Illuminate\Http\*`.

The bar: `new CreateCustomer(...)` must be constructible with mocks alone — no container, no migrations, no database.

## Shared kernel

`app/Shared/Contracts/` holds the cross-domain ports, bound in `app/Providers/AppServiceProvider.php`:

- `Clock::now(): DateTimeImmutable` → `SystemClock`. Injecting time makes timestamps assertable and date rules testable.
- `IdGenerator::next(): string` → `UuidGenerator` (`Str::uuid7()`, time-ordered). Lets an entity carry its identity before it is persisted.
- `TransactionManager::run(callable): mixed` → `EloquentTransactionManager`. Keeps `DB::transaction()` out of use cases.

Inject them; do not recreate them. Add a new shared port only when two or more domains need it — or when the consumer is the HTTP kernel, which is not a domain and has no `Contracts/` of its own: `BusinessMembership` and `BusinessTeamKey` are there for that reason, each implemented by the domain that owns the data.

**Domain exceptions carry their own HTTP classification.** `Exceptions/` may not import `Illuminate`, so the mapping cannot be a `render()` method on the exception; and a central `match` over class names in `bootstrap/app.php` is the growing switch this project forbids. Instead an exception implements `App\Shared\Contracts\DomainFailure`, returning a stable `errorCode()` — which is also its key under `messages.errors` — and a `DomainFailureKind`. `App\Http\Exceptions\RenderDomainFailure` turns the kind into 422/409/404/403/401 and the code into a translated message, registered once against the interface. A new exception needs no wiring, only the two methods and a key in **both** locale files.

The message on the wire is always the translation, never `getMessage()`: those are English developer strings that interpolate identifiers.

## Routes

Each domain owns `Infrastructure/Http/routes.php`, loaded by its service provider:

```php
Route::prefix('api')->middleware('api')->group(__DIR__.'/Infrastructure/Http/routes.php');
```

`routes/api.php` is only for app-wide endpoints. Domain providers are registered in `bootstrap/providers.php`.

## Starting a new domain

Never hand-create the tree. Declare the columns and the generator propagates them through the entire slice — migration, model, factory, entity, DTOs, mapper, FormRequest and Resource:

```sh
artisan make:domain Customers \
  --field="name:string" \
  --field="email:email:nullable" \
  --field="phone:string:nullable"
```

Field syntax is `name:type[:modifier]…`. Modifiers: `nullable`, `unique`, `index`.

| Type | Column | PHP | Cast |
| --- | --- | --- | --- |
| `string` `text` `email` `uuid` | same | `string` | — |
| `integer` `bigInteger` | same | `int` | `integer` |
| `boolean` | `boolean` | `bool` | `boolean` |
| `decimal(8,2)` | `decimal` | `string` | `decimal:2` |
| `float` | `float` | `float` | `float` |
| `date` `datetime` | same | `DateTimeImmutable` | `immutable_date(time)` |
| `json` | `json` | `array` | `array` |

`decimal` maps to `string` because that is what Laravel's `decimal:` cast returns — money is usually better modelled as `integer` cents.

The declared fields also drive behavior: the first required textual field gets the not-empty invariant, the first `unique` field produces `existsBy<Field>()` on the port plus the duplicate guard in the use case, and an `active:boolean` field produces `deactivate()`.

Other options: `--entity=Person` when `Str::singular` guesses wrong, `--root` for a domain outside tenancy, `--force` to overwrite an existing domain (also rewrites its migration).

Run without `--field` in a real terminal and it prompts for the columns. **In an agent or any non-TTY context always pass `--field`** — a prompt cannot be answered there, and the command falls back to a single `name:string`.

The output is a starting point, not a contract — replace the placeholder invariants, trim the repository port to the queries actually needed, and delete what the module does not use.

Templates live in `stubs/domain/` and `stubs/shared/`; the field parser is `app/Console/Commands/Support/DomainField.php`. Change the generated style there, not by editing every copy.

### What the generator cannot do

A generated slice **looks** finished and is not. Work through this list before calling a domain done:

| Gap | What you must write by hand |
| --- | --- |
| **No foreign keys between domains** | The only FK it emits is the hardcoded `business_id` → `businesses.uuid`, which **contradicts the int-FK rule above** — rewrite it to `businesses.id` by hand. For any other relation, declare nothing and hand-write `foreignId('customer_id')->constrained()`, the relation, and any eager loading |
| **No enums**, despite the convention below | The backed enum class, the cast, and `Rule::enum()` in the FormRequest |
| **No pivot tables** | The whole slice — migration, model, repository methods |
| **Single-column indexes only** | Every composite, partial or expression index |
| **`unique` is global, not per-tenant** | A `unique` modifier makes the column unique across *all* businesses, while the generated `existsBy<Field>()` is tenant-scoped — the constraint and the guard disagree. For per-tenant uniqueness write `unique(business_id, lower(email)) where deleted_at is null` by hand and drop the modifier |
| **Create-only CRUD** | `index`, `show`, `update`, `destroy`, their use cases, and pagination. Only `store` is generated |
| **No tests** | All of them |
| **No `ValueObjects/`, `Services/`, `Jobs/`, `Listeners/`, `Policies/`** | Create the folder when the first file needs it |
| **Nullable `date`/`datetime` is buggy** | `DomainField::requestAccessor()` checks the date branch before the nullable branch, so an omitted nullable date becomes **now** instead of `null`. Hand-write the accessor, or keep nullable timestamps out of the `--field` list entirely |
| **`--force` leaves orphans** | Re-running with different fields does not delete files from the previous run. Check `Exceptions/` for classes nothing throws |

Two footguns that are not about missing features:

- **Provider registration is not idempotent.** `registerProvider()` looks for the FQCN, but Pint then rewrites `bootstrap/providers.php` to `use` imports plus short class names, so the next run's check misses and appends a duplicate. Duplicated providers register every domain route group twice. Check that file after every `make:domain`.
- **`decimal` casts to PHP `string`.** Money belongs in `integer` cents; anything else needs a value object.

## Frontend

React 19 + Vite 8 + TypeScript (strict) in `resources/js`, styled with Tailwind 4 and shadcn/ui.

**This is not an SPA.** Laravel owns routing, and Inertia renders the page component for the matched route.

**Inertia renders pages; it does not carry data.** A controller passes only page identity and route parameters — a slug, an id, nothing that belongs in a Resource. The page component then loads what it needs from `/api` with axios when it mounts.

That split is the whole point: **one data contract**, the same endpoints the native client will call, instead of a web path through Inertia props and a second, drifting path through the API. The cost is a request after first paint; the benefit is that no endpoint exists only for the web.

```php
// A controller hands over identity, never the record itself.
return Inertia::render('admin/customers/index');
return Inertia::render('public/businesses/show', ['slug' => $slug]);
```

```
resources/js/
├── app.tsx                 createInertiaApp, resolves ./pages/**/*.tsx
├── layouts/                AdminLayout, PublicLayout, AuthLayout
├── pages/                  mirrors the Inertia page name, which mirrors the URL
│   ├── admin/…             the authenticated business dashboard
│   ├── public/…            the catalog and booking funnel
│   └── auth/…              login, register, password reset
├── domains/<domain>/       the per-domain contract — mirrors app/Domains/<Domain>
│   ├── types.ts            transcribed from Infrastructure/Http/Resources/
│   ├── api.ts              axios calls, transcribed from routes.php and Requests/
│   ├── queries.ts          the hooks pages actually call
│   └── components/
├── components/             grouped by who consumes them — see below
│   ├── ui/                 shadcn primitives, generated by the CLI
│   ├── shared/             used by two or more audiences
│   ├── form/               audience-agnostic wrappers over ui/
│   └── public/…            used by exactly one audience, split by surface
├── locales/<lang>/         i18next bundles, one JSON per namespace
├── content/legal/          the legal documents — see below
├── types/                  ambient declarations (i18next keys, Inertia page props)
└── hooks/  lib/
```

`content/` is for long-form prose that is neither UI copy nor data: today the three legal documents, as `content/legal/<lang>/{terms,privacy,cookies}.md`, plus `content/legal/entity.ts`, which holds every identity fact the documents interpolate as `{{TOKEN}}` and the date each was last revised. They are markdown rather than i18next keys for two reasons: a lawyer reviewing a `.md` diff needs no developer, and `lib/i18n.ts` bundles every locale namespace **eagerly**, so prose that long would ride into the main chunk for three pages almost nobody opens. `LegalDocument` reaches them through `import.meta.glob(…, { query: '?raw' })`, which code-splits each file. A fact with a `null` value renders as a visible marker naming what is missing, never as an empty string — an unfinished contract has to look unfinished.

**Audience lives in `pages/`, domain lives in `domains/`.** Inertia resolves a page by the string name the controller passes, so `pages/` has to mirror the URL — which makes the `admin` / `public` / `auth` split fall out naturally. Domains stay audience-agnostic, so `domains/customers/api.ts` is written once and used by both sides instead of being duplicated per audience.

Rules:

- **Pages are thin.** Layout, composition, and the hooks they call. No fetching logic, no `axios` import.
- **A page imports from its domain; a domain never imports from `pages/`.**
- **Nothing crosses between domains.** When two need the same thing, promote it to `components/`, `hooks/` or `lib/` — never reach sideways. Those three may never import from `domains/`.
- **`components/` is grouped by audience too**, so the path answers "who uses this, and is it used in more than one place?" without opening the file. `ui/` is the generated shadcn layer and is never hand-edited; `form/` holds audience-agnostic wrappers over it; `shared/` is for components **two or more audiences actually import**; everything else sits under its own audience — `public/landing/`, `public/hero/`, `public/shell/` — grouped by the surface it belongs to. A component earns `shared/` by gaining a second consumer, never in anticipation, and **the move is the signal**: when a second audience imports it, relocate it rather than leaving it where it was.
- **The domain folder exists only when its backend domain has an HTTP slice**, and takes the kebab-case of `app/Domains/<Domain>` keeping the plural: `Customers` → `customers`. That one word is then identical in the folder name, the query key, the URL and the API path.
- **No front-end entities, mappers, ports or barrel files.** It is a flat mirror of the backend's domains, not a copy of its layering.

The contract is transcribed, never invented: response types from each domain's `Infrastructure/Http/Resources/`, payload types from its `Requests/`, endpoints from its `routes.php`. Resources wrap in `data`, never serialise `business_id`, and `id` is always the uuid string.

Other conventions:

- Auth is the Sanctum **session cookie** — same origin, so `resources/js/lib/api.ts` sets `withCredentials` and `withXSRFToken`. No token ever lives in JavaScript.
- `/sanctum/csrf-cookie` sits on the `web` group at the domain root, **outside** the axios `baseURL: '/api'` — request it with `{ baseURL: '/' }`.
- Tailwind 4 is **CSS-first: there is no `tailwind.config`.** Theme tokens are CSS custom properties in `resources/css/app.css` under `@theme inline`.
- shadcn/ui, style `radix-nova`, base color `neutral`. `components/ui/*` is generated by the CLI — wrap it, never hand-edit it.
- `resources/js/lib/utils.ts` re-exports `cn` from the `cn` package, a compiled drop-in for `clsx` + `tailwind-merge`. It is intentional; do not swap it for the shadcn default.
- Import through the `@/*` alias → `resources/js/*`, declared in both `tsconfig.json` and `vite.config.ts`.

**State of play.** Inertia, `@tanstack/react-query`, i18next and Fortify are installed and wired: `app.tsx` runs `createInertiaApp`, `resources/views/app.blade.php` is the root view, `HandleInertiaRequests` and `SetLocale` are registered, and the `Ping` scaffolding and `GET /api/ping` are gone. Built so far: four layouts, the public landing page, the four Fortify auth pages, and an admin dashboard that already demonstrates the contract — it renders from `Inertia::render('admin/dashboard')` with no props and reads `/api/user` on mount.

**`domains/` now exists**, created by the onboarding screen: `domains/industries/` (the catalog the combobox reads) and `domains/businesses/` (the live name check and the create mutation). They are the worked example of the rules above — audience-agnostic, no barrel files, contract transcribed from the Resources. Note the one composition rule they demonstrate: the onboarding form needs both domains, and it gets them because the **page** calls each domain's hook and passes the result down. A domain never imports another domain.

`components/form/` holds the audience-agnostic wrappers that screen uses — `ComboboxField` (an in-flow listbox, deliberately not a popover, because at phone width an anchored layer collides with the keyboard), `PhoneField` and `FieldMessage`, which owns the one rule every field shares: a server `error` supersedes a `hint`. `lib/http.ts` is the only module that knows Laravel's `{ message, errors }` envelope, so a 422 through axios renders exactly like a 422 through a Fortify post.

**A form-level server message is a toast, not an inline alert.** `useServerErrors` splits what the server said in two: field messages go to the fields through `FieldMessage`, and the one sentence that is about the submission as a whole goes to `sonner`, mounted once as `components/shared/AppToaster` in `app.tsx` so a toast survives the navigation that follows a successful submit. `hooks/use-error-toast.ts` owns the lifetime, and the rule it enforces is that **a server error toast never auto-closes** — a flat 422 carries no `errors` key, so no field turns red and that sentence is the only explanation there is. The earlier `FormAlert` is gone: one message belongs in one place, and an alert re-rendered with an identical message announced nothing on a second identical refusal, while an imperative toast does.

```sh
npx tsc --noEmit     # typecheck
npm run build
npm run dev
```

## Tooling

**Delegation is the default.** The main session plans, reads, reviews the result and runs `pint`/`artisan test`; it does not write feature code itself.

| Work | Agent |
| --- | --- |
| `app/`, `database/`, `routes/`, `config/` — production code | **`mizita-backend`** (`.claude/agents/mizita-backend.md`). Owns this architecture, writes production code only; never tests. |
| `tests/` — the Pest suite | **`mizita-tester`** (`.claude/agents/mizita-tester.md`). Writes tests only; never production code. |
| `resources/js`, `resources/css` — UI | **`mizita-frontend`** (`.claude/agents/mizita-frontend.md`). Writes front-end code only; never PHP, never tests. Playwright reaches it as MCP tools from `.mcp.json`. |
| A new module | the **`/new-domain`** skill (`.claude/skills/new-domain/`), a wrapper over `make:domain`. |

A feature that crosses layers runs them in order: backend → tester → frontend.

### When the main session may edit directly

Only changes that are both small and unverifiable by tests:

- typos, comments, docblocks, formatting, a local variable rename
- `CLAUDE.md`, `README`, `.env.example`, editor and CI config
- a one-line change with no behavioural effect

**Override — behaviour beats size.** A change to `app/` that alters behaviour at all is delegated no matter how few lines it is, because it has to be covered or re-verified by the suite: `mizita-backend` makes the change, `mizita-tester` writes or updates the test. A green existing suite is not a reason to skip this.

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

## Testing

- **Tests run on PostgreSQL, never sqlite.** Exclusion constraints, partial and expression indexes and `timestamptz` are all Postgres-only, and sqlite would report green on a double booking. `phpunit.xml` points at the `pgsql` connection and the `mizita_api_testing` database; create it once per machine with `createdb mizita_api_testing`.
- **Only unit tests are written right now.** `tests/Feature/` holds what already exists and stays green, but new coverage goes in `tests/Unit/` until the feature-test switch in `.claude/agents/mizita-tester.md` is turned on.
- Unit tests build a use case **with mocks alone** — no container, no migrations. That is the bar the whole architecture exists to protect.
- Shared fakes belong in `tests/Support/`: `FakeClock`, a deterministic `FakeIdGenerator`, `FakeBusinessContext`. Injecting a fake context is how tenant isolation gets asserted without a database. **None of them exist yet** — the first test that needs one writes it (`autoload-dev` already maps `Tests\` to `tests/`, so no configuration change).
- Architecture tests belong in `tests/Arch/` and should encode the layer table above — a domain entity importing `Illuminate\*` is a test failure, not a review comment. **`tests/Arch/` does not exist yet** and `pest-plugin-arch` is installed and unused.
- **Coverage status: `Businesses` and `Customers` have no tests at all.** `tests/Unit/` holds only localization and Fortify password rules; no entity, use case or mapper in either domain is covered. Nothing about the architecture is currently enforced by the suite.

## Clean code practices and SOLID principles

Requirements on both stacks, PHP and TypeScript alike. The layered architecture above exists to make them achievable — it does not grant them, so every change is held to them.

The five SOLID principles, in this project's terms:

| Principle | Here it means |
| --- | --- |
| **Single Responsibility** | A use case orchestrates, an entity holds the rules, a mapper translates, a repository persists, a controller adapts HTTP. On the front end a component either fetches, arranges, or renders — never all three. Needing the word "and" to describe a class means it is two classes |
| **Open/Closed** | New behaviour is a new implementation of a port, a new use case, or a new variant — never a growing `match` on a string, and never a new boolean flag on an existing entry point |
| **Liskov Substitution** | Every adapter honours its port's full contract — same return types, same nullability, same exceptions. Every wrapper over a `components/ui/*` primitive stays substitutable for it |
| **Interface Segregation** | Narrow ports and narrow props: the consumer declares only what it calls. Same rule as [Crossing domains](#crossing-domains), applied inside a domain too |
| **Dependency Inversion** | Use cases depend on `Contracts/`; components depend on hooks. Concrete classes, Eloquent and URLs live at the edges |

Clean code practices, the checkable floor:

- Names state intent — no `$data`, `$info`, `$temp`, no abbreviations. A method name should let you predict its body.
- Guard clauses and early returns over nesting; no `else` after a `return`.
- No magic numbers or strings — a named constant or a backed enum.
- No boolean flag parameters: `publish()` / `unpublish()`, never `setPublished(bool)`.
- A rule attached to a primitive — email, slug, money, timezone, duration — is a value object, not validation scattered across use cases.
- **Tell, don't ask**: `$appointment->cancel($now)`, never read an entity's state at the call site and decide on its behalf.
- DRY with judgment — duplicate twice before abstracting, and **never DRY across domains**: shared code there is coupling, and the answer is a port.
- **Comments are the exception, not the habit.** Write one only where a reader who understands the code would still ask *why* — a non-obvious decision, a constraint imposed from outside, a landmine that looks safe to change and is not. Everything else goes: no comment that restates the line under it, no docblock that repeats the signature, no section banner, no narration of an obvious guard clause. If a comment is needed to explain *what* a method does, rename the method. A file where most lines carry a comment is a file nobody reads the comments in. Delete dead code instead of commenting it out.

The full per-stack checklists and the self-check questions live in `.claude/agents/mizita-backend.md` and `.claude/agents/mizita-frontend.md`.

## Conventions

- PHP 8.3+: type everything, `final` by default, promoted readonly constructor properties, enums over string constants — **the generator cannot emit an enum, so write it by hand**.
- Clean code practices and SOLID principles are requirements, not preferences — see the section above.
- **Shape validation in FormRequests, business rules in the use case or deeper.** A FormRequest rules on presence, type, array structure and length bounds — `required`, `nullable`, `array`, `required_with`, `string`, `uuid`, `min`/`max`. Every judgement about whether a value is *acceptable to the business* — a country we operate in, a number that can be dialled, a name still free, an industry in the catalogue — moves inward, throws a `DomainFailure` from `Exceptions/`, and reaches the caller as a translated 422. No business rule may run in the presentation layer, in a FormRequest, a custom `ValidationRule`, a controller or any other entry point. The reason is not purity: a rule at the edge is a rule the console, the queue and the next transport do not get, and the use case has to make the same call anyway, so the judgement ends up made twice and eventually disagrees with itself.
- **Consequence for the client:** a shape failure is Laravel's `{message, errors}` 422, keyed by field; a business failure is a flat `{message, code}` 422. `resources/js/lib/http.ts` already renders both — `fieldErrorsFrom()` returns `{}` when there is no `errors` key, so the translated sentence surfaces as a form-level message.
- **A DTO builds itself, and builds its children.** Every input DTO carries a named constructor that takes the validated payload and returns a fully assembled object, nested DTOs included — `OnboardBusinessInput::fromRequest($request->validated(), $owner->uuid)`. A controller never reads a key out of the body, never casts, never composes a child DTO and never carries a private helper to do it: it takes the request, hands the payload over, and returns the response. Nothing else.
  The payload arrives as the validated **array**, not as the `Request` — `Application/` may not import `Illuminate\Http`, and `tests/Arch/LayerDependencyTest.php` fails the moment it does. The name stays `fromRequest` because that is what it means to a reader; the argument is what keeps the layer clean. A value the body cannot be trusted to carry — the authenticated caller's id above all — is a separate parameter, never a key the client could set.
- Authorization in policies/gates at the HTTP edge, unless the rule is genuine domain logic; responses through Resources.
- Eager-load in the repository adapter; use cases must not know about eager loading.
- Run `pint --dirty` after edits.
- All code, comments and documentation in English.
