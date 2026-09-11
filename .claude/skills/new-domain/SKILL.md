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

Conventions baked in: an auto-incrementing int `id` for internal joins plus a unique `uuid` carrying the public identity; `SoftDeletes` on every model; and, unless `--root`, a `business_id` uuid column referencing `businesses.uuid` with routes behind `['api', 'auth:sanctum', 'business']`.

The declared fields also drive behavior — the first required textual field gets the not-empty invariant, the first `unique` field produces `existsBy<Field>()` and the duplicate guard, and an `active:boolean` field produces `deactivate()`.

## After running

1. Review the generated migration, then `artisan migrate`.
2. Replace the placeholder invariants in `Entities/<Entity>.php` with the actual business rules.
3. Trim `Contracts/<Entity>Repository.php` to the queries the domain really needs, and update the Eloquent adapter to match.
4. Delete the parts of the slice the module does not need — the scaffold is a starting point, not a contract.

Verify with `artisan route:list --path=<prefix>`.

## Notes

- Templates live in `stubs/domain/` and `stubs/shared/`; the field parser is `app/Console/Commands/Support/DomainField.php`. Edit those to change the generated style.
- The architecture is documented in `CLAUDE.md`. The `mizita-backend` agent handles the feature work after scaffolding.
