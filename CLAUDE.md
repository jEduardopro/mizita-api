# mizita-api

Laravel 13 REST API. Backend only — the front end lives elsewhere.

## Stack

- Laravel 13.31, PHP 8.4+
- PostgreSQL (`mizita_api` locally)
- Sanctum, already wired for both session and token auth: `bootstrap/app.php` calls `statefulApi()`, and `withExceptions` forces JSON rendering for `api/*`. Do not re-add either.
- Pest for tests, Pint for formatting

## Running commands

`php` on `PATH` is **PHP 7.3** and every artisan call dies with a Composer `platform_check.php` fatal. Check `php -v` first and fall back to a PHP 8.4+ binary — on this machine `/opt/homebrew/opt/php/bin/php` is PHP 8.5.

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
│   ├── Gateways/
│   └── Http/                  Controllers/ Requests/ Resources/ routes.php
└── CustomersServiceProvider.php
```

Create a folder only when the first file needs it. Never scaffold empty directories.

### Layer dependency rules

| Layer | May import | Must never import |
| --- | --- | --- |
| Domain — `Contracts/ Entities/ ValueObjects/ Events/ Exceptions/` | plain PHP, `DateTimeImmutable`, same-domain classes | anything `Illuminate\*`, Eloquent, Carbon, `Infrastructure\*`, another domain |
| `Application/` | its own domain layer, `app/Shared/Contracts/`, framework *interfaces* only (`Illuminate\Contracts\Events\Dispatcher`, `ShouldQueue`, `Illuminate\Console\Command`) | Eloquent, facades, `Illuminate\Http\*`, `Infrastructure\*` |
| `Infrastructure/` | everything | — |

**Dependencies point inward only.** Wiring happens in the domain's service provider.

`Jobs/`, `Commands/` and `Listeners/` sit in `Application/` even though they extend framework base classes — they are the only exception there, and must stay thin wrappers around a use case.

The domain layer uses `DateTimeImmutable`, never `Carbon`.

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

## Multi-tenant: everything belongs to a Business

The system is multi-business. `Businesses` is the **root** domain — it is not scoped to itself. Every other domain is tenant-scoped by default.

Tenant-scoped tables carry `business_id` as a **uuid column referencing `businesses.uuid`**, not an int FK. That keeps the mapper free of any uuid→int lookup: the entity's `businessId` maps straight to the column. Each table still has its own int primary key for its own joins.

Tenancy is explicit in the domain, not magic:

- The entity carries `public readonly string $businessId`.
- The use case injects `App\Shared\Contracts\BusinessContext` and passes `businessId: $this->business->currentBusinessId()` into `Entity::create()`. A unit test injects a fake context and can assert isolation with no database.
- `App\Http\Middleware\SetBusinessContext` (alias `business`, registered in `bootstrap/app.php`) resolves the business from `$request->user()->business` and **aborts 403** when the user has none. It never binds a null context.
- `users.business_id` holds the business uuid; `User::business()` is the relation.
- `App\Shared\Infrastructure\Concerns\BelongsToBusiness` adds a global scope and a `creating` hook to the model. It is a safety net — the repository already writes `business_id` from the entity. When no context is bound (console, migrations, seeders) the scope is skipped, which is why HTTP isolation is guaranteed by the route middleware, not by the trait.

Tenant-scoped domains expose routes under `['api', 'auth:sanctum', 'business']`; root domains get `['api']`.

Generate a root domain with `--root`:

```sh
artisan make:domain Businesses --root --field="name:string" --field="slug:string:unique"
```

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

Inject them; do not recreate them. Add a new shared port only when two or more domains need it.

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

## Tooling

- Backend work → the **`mizita-backend`** agent (`.claude/agents/mizita-backend.md`). It owns this architecture and writes production code only; it does not write tests.
- Testing → the **`mizita-tester`** agent (`.claude/agents/mizita-tester.md`). It owns the Pest suite and writes tests only; it never touches production code.
- New module → the **`/new-domain`** skill (`.claude/skills/new-domain/`), a wrapper over `make:domain`.

## Conventions

- PHP 8.3+: type everything, `final` by default, promoted readonly constructor properties, enums over string constants.
- Validation in FormRequests, authorization in policies/gates at the HTTP edge, responses through Resources.
- Eager-load in the repository adapter; use cases must not know about eager loading.
- Run `pint --dirty` after edits.
- All code, comments and documentation in English.
