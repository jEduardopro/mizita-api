# Mizita API — Phones fix + Roles & Permissions model

> **Session handover.** This file is the full record of the 2026-09-12 planning session so the
> work can be resumed cold, without re-explaining anything. It contains: what was investigated,
> what was found, every decision the product owner made (and why), and the implementation plan.
> Two `Plan` subagents were still producing detailed slice designs when the session ended; their
> output is **not** in this file. Everything they were asked to design is described below in
> enough detail to redo it.

---

## Context

Review of the first onboarding run against the local database surfaced four complaints. One turned
out not to be a defect; the other three are real and are the scope of this work.

| # | Reported | Verdict |
| --- | --- | --- |
| 1 | `businesses.name` was stored slugified (`dental-y` in both `name` and `slug`) | **Not a defect.** Closed — see below |
| 2 | `roles.business_id` is `NULL` and the purpose of the column is unclear; platform-level roles are also wanted | **Real work.** The NULL is by design, but the model has to grow |
| 3 | `permissions` needs `description` (a translation key) and a kebab `slug` | **Real work** |
| 4 | `phones.phoneable_id` holds a uuid instead of the int primary key, and there is no real phone validation (a Mexican number was accepted as `US`) | **Real work**, the most urgent of the three |

### 1 is closed — no code change

The whole path was traced: `BusinessOnboardingForm.payloadFrom()` sends `name.trim()` →
`CreateBusinessRequest` does not transform → `BusinessController::store` passes
`$request->string('name')` → `OnboardBusiness::handle` does `trim($input->name)` and derives the
slug separately with `Slug::fromName($name)` → `BusinessMapper::toAttributes` writes `name` and
`slug` from two different accessors. `grep -rn "Str::slug"` over `app/ database/ routes/` hits only
`ValueObjects/Slug.php`. No middleware, no DB trigger, no generated column.

The product owner confirmed manually: **the literal string `dental-y` was typed into the name
field.** The row is correct. Nothing to fix.

Worth knowing, though, for later: `POST /api/businesses` has **no feature test at all**, which is
why there was nothing to point at. `OnboardBusinessTest` only exercises the use case with mocks.

---

## Decisions made by the product owner

These were answered explicitly during the session. Treat them as requirements; do not relitigate.

### Roles & permissions

1. **Two planes, conceptually: platform and business.** Platform roles are assigned with a
   `NULL` team; business roles with `team = businesses.id`. Confirmed as the target shape.
2. **The platform plane is NOT implemented in this batch.** No platform roles seeded, no
   `platform:grant` artisan command, no platform middleware, no `/api/platform/*` endpoints, no
   screens. Quote: *"no esto aun no lo implementes yo te indico cuando necesitemos trabajar esta
   feature"*. Only the schema is left honest (the `scope` column exists).
3. **`permissions` and `roles` both get three new columns**: `slug` (kebab of the name:
   `business.manage` → `business-manage`), `description` (a **translation key**, never prose:
   `permissions.business.manage.description`), and `scope` (`'platform' | 'business'`).
   `scope` is a security invariant: a business owner must never be grantable a platform permission,
   and the UI lists only what applies.
4. **Human-readable text lives in locale files, never in the database**: new
   `lang/{en,es}/permissions.php` and `lang/{en,es}/roles.php`, each entry carrying `label` and
   `description`.
5. **One catalogue file: `config/authorization.php`.** It lists every permission (with `scope` and
   `module`) and every role with its permission list (`'*'` allowed, for `owner`). A seeder reads it
   and syncs. Chosen over a per-domain enum registry for reviewability — the whole access model is
   one diff.
6. **Per-business role rows (cloning).** Editable business roles — today only `staff` — exist as
   **templates** with `business_id = NULL` and are **cloned per business**
   (`roles.business_id = <business int key>`) when the business is created. The owner edits the
   permissions of *their* copy; no other business is affected.
   *Rationale, and the point that was initially contested:* Spatie's `revokePermissionTo()` /
   `syncPermissions()` do exist — that was never in doubt. The issue is scope: revoking a permission
   from a single **global** `staff` row would revoke it in every business on the platform. A
   per-business row is precisely what `roles.business_id` is for.
7. **The `owner` role stays global (`business_id = NULL`), is never cloned and is never editable.**
   Two reasons: an owner must not be able to lock themselves out of their own business, and the
   partial index `model_has_roles_single_owner_unique` names `SeededStaffRole::OWNER_ID` (= 1)
   literally, so the owner role's primary key must stay fixed.
8. **Owner can edit permissions of existing roles only** — no creating or deleting roles in the
   panel. A checkbox screen, not a CRUD.
9. **Membership rule to guarantee**: an account may be **owner of at most one business**, may be
   **staff at many businesses**, and has **at most one membership per business**. Quote: *"un staff
   puede ser owner de otro negocio, pero solo puede ser owner de uno por cuenta, y si puede ser
   staff en varios"*. This matches what the current indexes already do — but see the gap in
   *Open question A* below.
10. **No new HTTP endpoints and no React screens in this batch.** The Staff API
    (`GET/POST /api/staff`, role assignment, role permission editing) is a separate, later batch.

### Phones

11. **`phoneable_id` becomes `bigint`** — the int primary key, not a uuid. This explicitly
    overrides the justification currently written in the create-table migration's docblock. The
    project rule is that foreign keys reference the int primary key, polymorphic ones included.
12. **Install `giggsey/libphonenumber-for-php`** and do real validation and extraction. The
    reference style supplied by the product owner: parse with `PhoneNumberUtil`, fall back to
    forcing a `+` plus the dial code, then `isValidNumber()`, and extract with
    `format(E164)`, `getRegionCodeForNumber()`, `isValidNumberForRegion()`,
    `PhoneNumberOfflineGeocoder::getDescriptionForNumber()` and
    `PhoneNumberToTimeZonesMapper::getTimeZonesForNumber()`.
13. **Persist the useful facts as real columns**, timezones as json:
    `phoneable_id` bigint, `country_code` char(2) (the region libphonenumber reports),
    `calling_code` smallint, `national_number`, `e164` (indexed), `number_type`,
    `geo_description` (the state, e.g. `Tamaulipas`), `timezones` json.
14. **Keep the MX/US whitelist.** libphonenumber validates, and on top of that any country outside
    the supported list is rejected. The `CountryCode` enum and the `phones_country_code_check`
    constraint stay as they are.
15. **`customers.phone` is not migrated** in this batch, and **`customers.business_id` stays a
    uuid** in this batch. Both remain declared debt. Quote: *"Ninguna de las dos ahora"*.

---

## Verified findings (so nothing has to be re-investigated)

### Why `roles.business_id` is NULL — it is correct today

`StaffRoleSeeder` deliberately creates the two roles as **global definitions**: one row serves every
business, and the **assignment** in `model_has_roles` carries the business. The DB confirms it
works:

```
roles            id=1 owner business_id=NULL | id=2 staff business_id=NULL
model_has_roles  role_id=1 model_type=account model_id=2 business_id=1
```

What changes now is only decision 6: editable roles get per-business copies so their permission
lists can diverge.

### Spatie 8.3 team semantics — read from vendor source, load-bearing

`vendor/spatie/laravel-permission/src/Traits/HasRoles.php:75-76`

```php
return $relation->wherePivot($teamsKey, getPermissionsTeamId())
    ->where(fn ($q) => $q->whereNull($teamField)->orWhere($teamField, getPermissionsTeamId()));
```

Two separate filters, and both matter:

- **the pivot** (`model_has_roles.business_id`) must equal the current team id **exactly**. Because
  Laravel's `where($col, null)` becomes `whereNull`, calling `setPermissionsTeamId(null)` produces
  `model_has_roles.business_id is null` — which is a real, working global plane. **Spatie natively
  supports the platform plane**; no fork or workaround is needed.
- **the roles table** accepts either a `NULL`-team row (a template) **or** one belonging to the
  current team. So a cloned `staff` row with `business_id = N` resolves correctly once
  `setPermissionsTeamId(N)` has run, and so does the global `owner` row. **Both halves of the design
  are supported by the package as shipped.**

The one blocker for the platform plane later: `model_has_roles.business_id` is `NOT NULL` and part
of the composite primary key. Making it nullable means dropping that PK and replacing it with a
unique **expression** index — Postgres here is **14.22**, so `UNIQUE NULLS NOT DISTINCT` (15+) is
unavailable and the index must be
`create unique index … on model_has_roles (coalesce(business_id, 0), role_id, model_id, model_type)`.
The existing FK already tolerates a NULL, as its own docblock says. **Not part of this batch** — it
is a single migration when the platform plane lands.

Also noted: with teams on, the `roles` unique index is `(business_id, name, guard_name)`, and
Postgres treats NULLs as distinct — so duplicate *global* `owner` rows are **not** blocked by that
index. Only the fixed primary keys in the seeder prevent them. The new seeder must preserve that
property.

### The map of what exists today

**Spatie wiring**
- `config/permission.php`: `teams => true`, `team_foreign_key => 'business_id'`.
- `2026_09_12_120150_create_permission_tables.php` — stock Spatie migration.
- `2026_09_12_120250_add_single_owner_index_to_model_has_roles.php` —
  `create unique index model_has_roles_single_owner_unique on model_has_roles (model_type, model_id) where role_id = 1`.
  Enforces one owner role per account platform-wide. **Fragile**: an index predicate cannot look a
  role up by name, so it silently stops being true if the owner role's id ever moves.
- `2026_09_12_120450_add_business_foreign_key_to_model_has_roles.php` — FK
  `model_has_roles.business_id → businesses.id` cascade.
- `database/seeders/StaffRoleSeeder.php` — fixed PKs from `SeededStaffRole` (`OWNER_ID = 1`,
  `MEMBER_ID = 2`); permissions `staff.manage`, `business.manage`; grants rather than syncs;
  `syncRoleIdSequence()` bumps the Postgres sequence past the hand-written ids.
- `App\Domains\Staff\Infrastructure\Permissions\StaffRoleAssignments` — **the only class that knows
  Spatie exists**. `assign(User, int $businessKey, StaffRole)`, `roleFor(User, int $businessKey)`,
  `ownsAnyBusiness(User)`. Mirrors the three table/column names as constants rather than reading
  config.
- `App\Domains\Staff\Infrastructure\Gateways\EloquentBusinessMembership` — joins
  `model_has_roles`/`roles` **by hand** (it runs before any team is set) and orders owner-first with
  `orderByRaw('case roles.name when ? then 0 else 1 end', ['owner'])`.
- `App\Http\Middleware\SetBusinessContext` (alias `business`) — resolves the tenant from memberships
  plus an optional `X-Business` header, binds `RequestBusinessContext`, and calls
  `setPermissionsTeamId($teamKeys->teamKeyFor($businessId))`.

**Staff**
- `staff_members`: `id`, `uuid` unique, `business_id` int FK, `account_id` int FK, timestamps,
  softDeletes, partial unique `(business_id, account_id) where deleted_at is null`. **No role
  column** — role is a Spatie assignment.
- `StaffRole` enum: `Owner = 'owner'`, `Member = 'staff'`.
- `RegisterBusinessOwner` guards with `ownsAnyBusiness()` → `AccountAlreadyOwnsBusiness`.
- **No HTTP slice at all.** The only entry is `Businesses\Infrastructure\Gateways\StaffOwnerRegistrar`.
- `EloquentStaffMemberRepository::save()` catches *any* `UniqueConstraintViolationException` and
  reports it as `AccountAlreadyOwnsBusiness`, so a membership collision and an owner collision are
  indistinguishable to callers. Worth tightening when the Staff API lands.

**Phones**
- Migration `2026_09_12_120100_create_phones_table.php`: `phoneable_type` string(32) holding a morph
  **alias**, `phoneable_id` **uuid**, `country_code` char(2), `national_number` varchar(15). Check
  constraints `phones_country_code_check` (`in ('MX','US')`) and `phones_national_number_check`
  (`^[0-9]{7,15}$`), partial unique `phones_owner_unique` on
  `(phoneable_type, phoneable_id) where deleted_at is null`.
- Morph aliases come from `PhoneOwnerType` (`business`, `staff_member`, `customer`) and are
  registered by each **owning** domain via `Relation::enforceMorphMap`. Phones registers nothing and
  reads `phoneable_type` as an opaque string — that is what keeps it from importing its consumers.
- Validation today is three layers that all hardcode the same weak rule (`^\d{7,15}$`, `MX|US`):
  the FormRequest regex, `PhoneNumber::fromParts`, and the DB check constraint. **None is carrier or
  format aware** — which is exactly how `+1 8421133471` got in.
- `InvalidPhoneNumber` extends `InvalidArgumentException` and is deliberately **not** a
  `DomainFailure` (it means validation was bypassed — a programming error, not a user error).
- Phones has **no HTTP slice on purpose**; a phone is always reached through its owner's endpoints.
- Only consumer: `Businesses\Contracts\PhoneBook` → `PhonesPhoneBook` → `AttachPhone`, called from
  `OnboardBusiness::register()` inside the transaction, with the business **uuid**.
- **There is exactly one row in the local `phones` table** (a `business`), so the uuid → bigint
  conversion has essentially no data to migrate.
- `giggsey/libphonenumber-for-php` is **not** installed. Neither is `propaganistas/laravel-phone`.

**Shared kernel / error rendering**
- `App\Shared\Contracts`: `BusinessContext`, `BusinessMembership`, `BusinessTeamKey`, `Clock`,
  `IdGenerator`, `TransactionManager`, `DomainFailure`.
- `DomainFailureKind`: `Invalid` 422, `Conflict` 409, `NotFound` 404, `Unauthenticated` 401,
  `Forbidden` 403 — mapped in `App\Http\Exceptions\RenderDomainFailure`, registered once in
  `bootstrap/app.php` against the interface. The wire message is always
  `__('messages.errors.'.$failure->errorCode())`; `getMessage()` is never sent.
- `lang/{en,es}/messages.php` is a single top-level `errors` array keyed by `errorCode()`. Parity is
  enforced by `tests/Unit/Localization/TranslationParityTest.php`, so **every new key must land in
  both files or the suite fails**.

**Frontend touchpoints**
- `resources/js/lib/phone.ts` — `SUPPORTED_PHONE_COUNTRIES`, with dial codes **duplicated** from
  `CountryCode::dialCode()` in PHP.
- `resources/js/components/form/PhoneField.tsx` — native `<select>` plus `type="tel"` input, no mask
  and no `maxLength` on purpose (the server is the authority).
- `resources/js/domains/businesses/types.ts` — `BusinessPhonePayload { country_code, national_number }`.
- Pre-existing cosmetic bug, unrelated but noted: `PhoneField` expects its `label` to already carry
  the dial code, but `BusinessOnboardingForm` passes `t('onboarding.phone.countries.MX')`, which is
  just `"México"` — so the `(+52)` never reaches the UI.

---

## Plan

Two independent slices. **Phones first** — it is smaller, it is the actual data-integrity bug, and
it does not touch the access model.

Per `CLAUDE.md`, the main session does not write feature code: **`mizita-backend` → `mizita-tester`
→ `mizita-frontend`**, in that order, then `pint --dirty` and `artisan test`.

### Slice A — Phones

**A1. Install the library**
`composer require giggsey/libphonenumber-for-php`.

**A2. Migration**
A new `alter` migration (do not rewrite the create-table migration — it is already applied and the
project treats applied migrations as history). It must:
- convert `phoneable_id` from `uuid` to `bigint`, resolving the single existing row's business uuid
  to `businesses.id`;
- drop and recreate `phones_phoneable_index` and the partial unique `phones_owner_unique` on the new
  column type;
- add `calling_code` smallint, `e164` (indexed), `number_type`, `geo_description` nullable,
  `timezones` json;
- keep `phones_country_code_check` (`in ('MX','US')`) per decision 14;
- correct the now-false docblock in the original create-table migration is **not** possible (history)
  — the new migration's docblock states the reversal and why.

**A3. Parsing behind a port**
The domain layer may not import `Illuminate`, and while libphonenumber is pure PHP, a use case must
stay constructible with mocks alone. Declare `PhoneNumberParser` in `Contracts/`, returning a value
object carrying the parsed facts; implement it in `Infrastructure/` with libphonenumber. A fake
parser then makes every 422 path testable with no library and no database.

Where the value objects live is a judgement call the backend agent must make against the rule that
`app/Shared/` is only for what two or more consumers need: `PhoneNumber` and `CountryCode` are
already shared (the Businesses FormRequest and controller use them), so the parsed-facts object
probably belongs beside them, while `PhoneOwnerType` stays in the Phones domain.

**A4. `number_type` backed enum**
The generator cannot emit enums. Map libphonenumber's `PhoneNumberType` int constants to a backed
enum (`mobile`, `fixed_line`, `voip`, …) in one place.

**A5. uuid ↔ int for a morph target**
The entity keeps carrying the owner **uuid**; the repository adapter translates. Preferred approach:
`Relation::getMorphedModel($alias)` resolves the alias to the owning model class **without Phones
importing Businesses/Customers/Staff**, since every owning domain already registers its alias with
`enforceMorphMap`. The alternative — a `PhoneOwnerDirectory` port implemented per owner domain — is
more machinery for the same result. Either way it must work **inside `OnboardBusiness`'s
transaction**, immediately after the business row is inserted.

**A6. Rejecting an invalid number**
A custom validation rule used by `CreateBusinessRequest`, with its message in
`lang/{en,es}/validation.php`. A `DomainFailure` is probably unnecessary: this is input validation at
the HTTP edge, and `InvalidPhoneNumber` should stay a programming-error exception.

**A7. Ripple**
`Phone` entity, `PhoneMapper`, `EloquentPhoneRepository`, `PhoneModel`, `PhoneModelFactory`,
`AttachPhone`, `AttachPhoneInput`, `PhoneData`, `PhonesPhoneBook`, `CreateBusinessRequest`,
`BusinessController::phoneFrom()`.

**A8. Frontend**
Only what is needed to keep onboarding working, plus the new validation message. `mizita-frontend`.

### Slice B — Roles & permissions

**B1. Migration** — add `slug`, `description`, `scope` to both `permissions` and `roles`, and
backfill the two existing permissions and two existing roles so the seeder has something consistent
to sync against.

**B2. `config/authorization.php`** — the single catalogue. Shape:

```php
'permissions' => [
    'business.manage' => ['scope' => 'business', 'module' => 'businesses'],
    'staff.manage'    => ['scope' => 'business', 'module' => 'staff'],
],
'roles' => [
    'owner' => ['scope' => 'business', 'template' => false, 'permissions' => '*'],
    'staff' => ['scope' => 'business', 'template' => true,  'permissions' => []],
],
```

Keep the catalogue **honest**: only permissions whose feature exists. `Services` and `Appointments`
do not exist yet, so `services.create` and friends are **not** seeded — the list grows one line per
feature, as `StaffRoleSeeder`'s current docblock already argues.

**B3. Seeder** — replaces `StaffRoleSeeder` conceptually. Idempotent, reads the config, syncs
permissions and each role's permission list. Must preserve `OWNER_ID = 1` / `MEMBER_ID = 2` and keep
`syncRoleIdSequence()`.

**B4. Role cloning at business creation** — templates (`business_id = NULL`) are cloned per business.
Wired through a port declared by the **consuming** domain (`Businesses`) with a gateway in
`Businesses/Infrastructure/Gateways/`, bound in `BusinessesServiceProvider`, and called from inside
`OnboardBusiness`'s transaction. The `owner` role is never cloned.

**B5. Interaction audit** — verify against the vendor source that cloning does not break
`StaffRoleAssignments::assign()` / `roleFor()`, `EloquentBusinessMembership::businessIdsFor()`
(which matches roles **by name** and orders owner-first — a cloned `staff` row still has the name
`staff`, so it should hold, but this must be confirmed), and `SetBusinessContext`.

**B6. Permission middleware** — register an alias so routes can require a permission. Prefer a thin
project-owned middleware over exposing Spatie's shipped `role`/`permission` aliases directly, to keep
the package name out of route files.

**B7. Locale keys** — new `lang/{en,es}/permissions.php` and `lang/{en,es}/roles.php`, plus any new
`messages.errors.*`. **Both locales, always**, or `TranslationParityTest` fails.

### Tests (`mizita-tester`, after each slice)

Only unit and architecture tests — `tests/Feature/` is frozen by the switch in
`.claude/agents/mizita-tester.md`. The named gap that must be closed:

- **`+1 8421133471` must be rejected** — the exact number that started this.
- `+52 8421133471` accepted, and the extracted region/state/timezones asserted.
- `+34 600123456` rejected by the MX/US whitelist even though libphonenumber says it is valid.
- Parser port fake; `AttachPhone` still constructible with mocks alone.
- `number_type` mapping for every libphonenumber constant.
- Seeder idempotency; a cloned role's permission list diverging without affecting another business;
  the owner role never being cloned.
- The membership rule: owner of one business, staff at many, one membership per business.

### Verification

```sh
/opt/homebrew/opt/php/bin/php vendor/bin/pint --dirty
/opt/homebrew/opt/php/bin/php artisan test
npx tsc --noEmit
```

Then, end to end: `artisan migrate:fresh --seed`, run the onboarding form, and confirm in the
database that (a) `phones.phoneable_id` holds the business's **int** id, (b) the extracted region,
state and timezones are populated, (c) `+1 8421133471` is rejected with a translated 422, and
(d) the new business has its own cloned `staff` role row while `owner` stays global.

---

## Open questions for the next session

**A. One role per (account, business).** Nothing currently prevents a single account from holding
**both** `owner` and a `staff` role at the **same** business, because `model_has_roles`' composite
primary key includes `role_id`. `StaffRoleAssignments::assign()` uses `syncRoles()`, so the
application never writes both — but the database does not forbid it. A partial unique index on
`(business_id, model_id, model_type)` would close it. This needs a decision, and the cloned-role
design does not change the answer (the index does not name any role id).

**B. Does the owner role get every permission implicitly, or an explicit list?** The catalogue uses
`'*'` for `owner`. Decide whether the seeder expands `'*'` to every `business`-scope permission at
seed time (explicit rows, auditable, needs a re-seed when a permission is added) or whether `owner`
short-circuits every check in code (always current, invisible in the database). Leaning explicit.

**C. When exactly are templates cloned?** At business creation (chosen above) means every existing
business needs a backfill migration, and a business created before a new role template is added will
never get it. A lazy clone on first edit avoids both, at the cost of a business having no role rows
until someone opens the screen. Worth revisiting when the Staff API batch starts.

**D. Deferred by explicit instruction, do not start without being asked**: the platform plane
(roles, `platform:grant` command, middleware, endpoints, screens), the Staff HTTP API and the team
management screens, `customers.phone` → `phones`, and `customers.business_id` uuid → int.




