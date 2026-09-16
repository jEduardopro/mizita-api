---
paths:
  - "database/**/*.php"
  - "app/**/*.php"
---

# Database

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

**A polymorphic `*_type` column names the real model.** Each model used polymorphically registers its alias with `Relation::enforceMorphMap()` in its own domain's service provider — `AppServiceProvider` for `App\Models\User`, which belongs to no domain — and the alias is the snake_case of the class name with the `Model` suffix dropped: `user`, `business`, `staff_member`. Never a synonym from the ubiquitous language: `account` for `User` is exactly the mistake the rule forbids, because a row then cannot be read without opening a provider to decode it. `PhoneOwnerType` may back the same strings, but the model decides them.

The two rules meet in the repository adapter, which is the only place that may hold both halves of an identity. An entity carries a neighbour's **uuid** (`$identity->accountId`), so the adapter resolves it to the int on the way in and reads the uuid back on the way out — eager-loading the relation rather than issuing a query per row. That translation never leaks above `Infrastructure/`.


## Soft deletes

Every Eloquent model uses `SoftDeletes` and every migration ends with `$table->softDeletes()`. The repository port exposes `delete(string $id): void`, so removal is reachable from the domain instead of being dead infrastructure.

A soft delete is the *record* lifecycle. An `active` style flag is a *business* state that lives on the entity — they are different things, and a domain may need both.

