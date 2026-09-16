---
name: new-domain
description: Scaffold a new DDD domain module in mizita-api (entity, port, DTOs, use case, Eloquent repository + factory, HTTP slice, provider, migration) with the columns the user wants. Use when starting a new backend module such as Customers, Orders or Appointments.
---

# Scaffold a new domain

Generates a complete vertical slice under `app/Domains/<Domain>/` from the columns you declare, so a new module starts from working code with its real fields.

## Step 1 — gather the fields first

**Do not run the command before you know the columns.** Ask the user, in one round:

1. **Domain name** — plural (`Customers`, `Appointments`). Taken from the skill argument when given.
2. **Fields** — for each one: name, type, and whether it is `nullable`, `unique` or `index`.
3. **Tenancy** — is this scoped to a business (the default) or a global `--root` domain? Only things like `Businesses` itself, or system-wide catalogs, are root.

Use `AskUserQuestion` when the domain is obvious enough to propose a field set, and offer a sensible default so they can accept in one click. Otherwise just ask in plain text.

Available types: `string`, `text`, `email`, `uuid`, `integer`, `bigInteger`, `boolean`, `decimal(p,s)`, `float`, `date`, `datetime`, `json`.

Note for the user when it comes up: `decimal` maps to a PHP `string` (that is what Laravel's `decimal:` cast returns), so money is usually better as `integer` cents.

## Step 2 — run it

```sh
/opt/homebrew/opt/php/bin/php artisan make:domain Customers \
  --field="name:string" \
  --field="email:email:nullable" \
  --field="phone:string:nullable"
```

Always pass `--field` explicitly rather than relying on the command's interactive prompts — Bash has no TTY here, and without fields the command falls back to a single `name:string` column.

`php` on `PATH` is PHP 8.5.5 and passes Composer's platform check, but use the absolute path above — the allowlist is keyed to it, and a bare `php` stalls on a permission prompt.

Options: `--entity=Person` when the derived singular is wrong, `--root` for a domain outside tenancy, `--force` to overwrite an existing domain (also rewrites its migration).

## What it produces

For `Customers` with those fields: the `Customer` entity, `CustomerRepository` port, `CreateCustomerInput`/`CustomerData` DTOs, the `CreateCustomer` use case, `CustomerModel` + `CustomerMapper` + `EloquentCustomerRepository` + `CustomerModelFactory`, controller/request/resource, a per-domain `routes.php` exposing `POST /api/customers`, `CustomersServiceProvider`, and the migration. The fields reach every one of those files.

It also registers the provider in `bootstrap/providers.php`, creates `app/Shared/` on the first run, and runs Pint.

Conventions baked in: an auto-incrementing int `id` for internal joins plus a unique `uuid` carrying the public identity; `SoftDeletes` on every model; and, unless `--root`, a `business_id` int foreign key onto `businesses.id` with routes behind `['api', 'auth:sanctum', 'business']`.

The declared fields also drive behavior — the first required textual field gets the not-empty invariant, the first `unique` field produces `existsBy<Field>()` and the duplicate guard, and an `active:boolean` field produces `deactivate()`. They also drive the input DTO's `fromRequest()` and `validate()`, and the use case opens with `$input->validate();`.

The generated code carries no comments. If you find one in the output, the stub under `stubs/domain/` is the bug.

## Field types

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

## What the generator cannot do

A generated slice **looks** finished and is not. Work through this list before calling a domain done:

| Gap | What you must write by hand |
| --- | --- |
| **No foreign keys between domains** | The only FK it emits is `business_id` → `businesses.id`, correct per the int-FK rule, together with the model's `business()` relation and the adapter's `BusinessTeamKey` translation. For any *other* relation, declare nothing and hand-write `foreignId('customer_id')->constrained()`, the relation, and any eager loading |
| **No enums**, despite the convention in `.claude/rules/backend/conventions.md` | The backed enum class, the cast, and `Rule::enum()` in the FormRequest |
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

## After running

1. Review the generated migration, then `artisan migrate`.
2. Replace the placeholder invariants in `Entities/<Entity>.php` with the actual business rules.
3. Fill in `validate()` on `Application/Dtos/Create<Entity>Input.php`. The generator emits one `private function validate<Field>()` per column from the same rules it wrote into the FormRequest; anything the FormRequest gained by hand — a `min:`, a nested array, a per-tenant rule — has to be mirrored there too, because that copy is the one a console command or a queued job actually runs. Anything needing a repository, a clock or a parser stays in the use case.
4. Add the `messages.errors` key for every new exception to **both** `lang/en/messages.php` and `lang/es/messages.php`, or `TranslationParityTest` fails.
5. Trim `Contracts/<Entity>Repository.php` to the queries the domain really needs, and update the Eloquent adapter to match.
6. Delete the parts of the slice the module does not need — the scaffold is a starting point, not a contract.

Verify with `artisan route:list --path=<prefix>`.

## Notes

- Templates live in `stubs/domain/` and `stubs/shared/`; the field parser is `app/Console/Commands/Support/DomainField.php`. Edit those to change the generated style.
- The architecture is documented in `CLAUDE.md` and `.claude/rules/backend/`. The `mizita-backend` agent handles the feature work after scaffolding.
