---
paths:
  - "app/**/*.php"
  - "database/**/*.php"
  - "routes/**/*.php"
  - "config/**/*.php"
  - "tests/**/*.php"
---

# Architecture: layered DDD per domain

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

**Never hand-create this tree.** `artisan make:domain` generates the whole slice from the columns you declare — reach it through the `/new-domain` skill, which also lists what the generator cannot do.

`Services/` holds logic that is genuinely domain logic but belongs to no single entity — the availability slot calculator is the motivating case. Same import rules as `Entities/`: plain PHP, no clock, no repository, no container. A domain service that needs to *fetch* something is a use case in the wrong folder.

## Layer dependency rules

| Layer | May import | Must never import |
| --- | --- | --- |
| Domain — `Contracts/ Entities/ ValueObjects/ Services/ Events/ Exceptions/` | plain PHP, `DateTimeImmutable`, same-domain classes, `app/Shared/ValueObjects/` | anything `Illuminate\*`, Eloquent, Carbon, `Infrastructure\*`, another domain |
| `Application/` | its own domain layer, `app/Shared/Contracts/`, framework *interfaces* only (`Illuminate\Contracts\Events\Dispatcher`, `ShouldQueue`, `Illuminate\Console\Command`) | Eloquent, facades, `Illuminate\Http\*`, `Infrastructure\*` |
| `Infrastructure/` | everything | — |

**Dependencies point inward only.** Wiring happens in the domain's service provider.

`Jobs/`, `Commands/` and `Listeners/` sit in `Application/` even though they extend framework base classes — they are the only exception there, and must stay thin wrappers around a use case.

The domain layer uses `DateTimeImmutable`, never `Carbon`.

## Crossing domains

A domain never imports another domain. When a use case needs a neighbour's data, **the consumer declares the port**:

1. A narrow interface in the *consuming* domain's `Contracts/`, returning that domain's own small DTO or value object — `Appointments\Contracts\ServiceCatalog::describe(string $serviceId): ServiceSnapshot`.
2. An adapter in the consuming domain's `Infrastructure/Gateways/` that implements it by calling the other domain's repository or use case — `Appointments\Infrastructure\Gateways\ServicesServiceCatalog`.
3. Bound in the consuming domain's service provider.

The consumer owns the shape of what it needs, so the neighbour's DTOs never leak across the boundary and the use case stays constructible with a mock. For the write side and anything asynchronous, use domain events plus `Application/Listeners/` instead.

This is the rule most likely to be broken by accident, because a direct import compiles and passes tests. It still couples two domains permanently.


## Entities vs Eloquent models

A domain entity is not an Eloquent model.

- `Entities/Customer.php` — pure PHP, zero framework imports. Private constructor, `create()` / `restore()` named constructors, behavior methods instead of setters, invariants that throw domain exceptions.
- `Infrastructure/Eloquent/Models/CustomerModel.php` — the `Model` suffix is mandatory.
- `Infrastructure/Eloquent/Mappers/CustomerMapper.php` translates both ways. The repository adapter is the only class touching both sides.
- Entities never leave the application layer: use cases return DTOs.


## Routes

Each domain owns `Infrastructure/Http/routes.php`, loaded by its service provider:

```php
Route::prefix('api')->middleware('api')->group(__DIR__.'/Infrastructure/Http/routes.php');
```

`routes/api.php` is only for app-wide endpoints. Domain providers are registered in `bootstrap/providers.php`.

