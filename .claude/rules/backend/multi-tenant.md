---
paths:
  - "app/**/*.php"
  - "database/**/*.php"
  - "routes/**/*.php"
  - "config/**/*.php"
  - "tests/**/*.php"
---

# Multi-tenant: everything belongs to a Business

The system is multi-business. `Businesses` is the **root** domain — it is not scoped to itself. Every other domain is tenant-scoped by default.

Tenant-scoped tables carry `business_id` as a foreign key onto **`businesses.id`**, the int primary key, per `database.md`. The entity still carries the business **uuid** in `public readonly string $businessId`, so the repository adapter translates between the two and nothing above `Infrastructure/` ever sees the int.

There is no longer an exception to this. `customers.business_id` held a uuid and was the last one; that table is gone, and `users.business_id` before it. Every tenant-scoped table now uses the int FK.

Tenancy is explicit in the domain, not magic:

- The entity carries `public readonly string $businessId`.
- The use case injects `App\Shared\Contracts\BusinessContext` and passes `businessId: $this->business->currentBusinessId()` into `Entity::create()`. A unit test injects a fake context and can assert isolation with no database.
- `App\Http\Middleware\SetBusinessContext` (alias `business`, registered in `bootstrap/app.php`) resolves the business from the caller's **staff membership** through `App\Shared\Contracts\BusinessMembership`, and **aborts 403** when the caller has none. It never binds a null context. A caller with more than one membership picks with an `X-Business` header carrying the business uuid, validated against their memberships; with no header it gets the owner membership, else the oldest. The middleware also calls `setPermissionsTeamId()` with the business's int key — without it every `can()` and `hasRole()` check silently matches nothing.
- `App\Shared\Infrastructure\Concerns\BelongsToBusiness` adds a global scope and a `creating` hook to the model. It is a safety net — the repository already writes `business_id` from the entity. When no context is bound (console, migrations, seeders) the scope is skipped, which is why HTTP isolation is guaranteed by the route middleware, not by the trait.

**The trait is incompatible with an int `business_id`**, so nothing uses it and `make:domain` no longer emits it: it writes and filters the column with the context *uuid*, so on a tenant table it would compare a bigint to a uuid and Postgres would raise `22P02`. Do not add it to any table until a sibling trait exists that memoises uuid→int per request. `staff_members` also omits it for a second reason: it is the table that *resolves* the tenant, so it must be queryable before any context is bound.

What replaces it is explicit and lives in the adapter: **`App\Shared\Contracts\BusinessTeamKey::teamKeyFor(uuid): int`** is the one translation from the business uuid an entity carries to the int the column stores. `make:domain` injects it into the generated repository, whose `save()` passes the resolved key into `toAttributes(Entity, int $businessKey)` and whose reads eager-load `business` so the mapper can take the uuid back off the relation. Isolation is still guaranteed by the route middleware and by business-scoped repository methods — there is no global scope, so a port must never expose a bare `findById($id)` on a tenant table.

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
| Editable by the owner | never | its **permissions**, yes — that is the entire point |

**What an owner edits is a role's permissions, never its name, and they cannot add or delete roles.** The set of role names is closed: it is whatever `config/authorization.php` declares, today `owner` and `staff`. That is what makes `StaffRole::from()` safe in `StaffRoleAssignments` — a name read back from `roles` is always an enum case, so the `ValueError` is unreachable by design rather than by luck. Do not "harden" it into `tryFrom` with a fallback; that would hide a genuinely broken row instead of failing on it. If role management ever ships, this is the call site to revisit first.

**The template is the config file, not a row.** There must never be a role row with `business_id IS NULL` named `staff`. `Role::findByParam()` matches `business_id IS NULL OR business_id = <team>` and returns `first()` with **no `ORDER BY`**, so a global template and a per-business clone sharing a name are ambiguous: `syncRoles('staff')` would attach an undefined one of the two, and if it picked the template every permission the owner edited would be silently inert. Keeping the template out of the table is what makes exactly one row match under a team — and why `StaffRoleAssignments` needs no special resolution logic. For the same reason `Role::create()` is banned on the clone path (it runs `findByParam` and can throw `RoleAlreadyExists` against the wrong row); use `Role::query()->firstOrCreate()`.

`owner` is global **and must stay that way**. `ownsAnyBusiness()` matches `roles.name = 'owner'` across every team, and `model_has_roles_single_owner_unique` names `role_id = 1` literally in its index predicate. Clone `owner` and an account can quietly come to own two businesses.

Consequences worth knowing before they bite:

- **The seeder syncs templates only and never a clone.** Re-seeding must not restore a permission an owner deliberately removed — that is a security change disguised as a deploy step.
- **A business created before a new permission is added does not receive it from the seeder.** Reaching existing businesses is an explicit backfill, not reference data.
- **`syncRoleIdSequence()` is runtime-critical, not tidiness.** Clones take sequence ids inside the onboarding transaction; a lagging sequence makes the first clone collide with `roles.id = 1` and breaks signup.
- **Spatie's permission cache is one blob holding every role row and every permission→role edge**, loaded on the first permission check of each request, so it grows linearly with the number of businesses. Revisit past a few thousand roles — and never shrink it by stripping `scope` from the cached columns, since a `Permission` read back with `scope === null` defeats the invariant that a business owner can never hold a platform permission.

The rules an account is held to: **owner of at most one business, staff at as many as it likes, exactly one role per business** — the last enforced by `model_has_roles_single_role_per_business`.

Planned and deliberately absent: the **platform plane** (roles assigned with a NULL team, for operating the system itself). The schema is ready for it — `scope` already distinguishes `platform` from `business` — but nothing is seeded, no middleware exists and no endpoint uses it. Landing it needs one migration: `model_has_roles.business_id` is `NOT NULL` and part of the composite primary key, and Postgres here is 14, so `UNIQUE NULLS NOT DISTINCT` is unavailable and the replacement is an expression index over `coalesce(business_id, 0)`. A `RequirePermission` middleware aliased `permission`, denying with `abort_if(..., 403, ...)` to match `SetBusinessContext` and never naming the permission in the message, is the intended shape when a route first needs one.

