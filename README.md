# Mizita

**Multi-tenant appointment booking.** A professional signs up, creates their business, and registers
their staff, services, customers and working hours. From then on they run their whole agenda from one
dashboard. On the public side, anyone can search across every published business — by business or by
service — and book an appointment, with or without an account.

Mizita is a web application first, but **the API is the contract**: every screen is built on the same
REST endpoints a native client will consume later. Nothing is rendered from data the API does not
also expose.

## Who it is for

- **Professionals and their staff** — hairdressers, clinics, studios, trainers, anyone whose business
  is booked time. They own a *Business*: its services, its people, its opening hours, its agenda.
- **End customers** — the people booking. They can book as a guest with just a name and a way to be
  reached, or create an account and keep their booking history across every business on the platform.

## Status

Early. Two domains exist today and both are create-only.

| Area | Today |
| --- | --- |
| Businesses | Create only, currently unauthenticated |
| Customers | Create only, tenant-scoped |
| Auth | Sanctum is wired for session and token auth, but no endpoints exist yet |
| Staff, services, availability, appointments | Not built |
| Public catalog and booking | Not built |
| Front end | Vite + React with a single scaffolding component |

## Stack

- **Laravel 13.31** on **PHP 8.4+**
- **PostgreSQL**
- **Sanctum**, for both session-cookie auth (the web app, same origin) and personal access tokens
  (the future native client)
- **Pest** for tests, **Pint** for formatting
- **React 19 + TypeScript** on **Vite 8**, with **Tailwind 4** and **shadcn/ui**

## Getting started

### The PHP binary — read this first

`php` on `PATH` in this project's development environment is **PHP 7.3**, and every `artisan` call
dies with a Composer `platform_check.php` fatal error. Check before you start:

```sh
php -v
```

If it is not 8.4+, use an explicit binary. On the maintainer's machine that is
`/opt/homebrew/opt/php/bin/php`, and every command below assumes you substitute yours.

### Install

```sh
git clone <repo> mizita-api && cd mizita-api

createdb mizita_api                       # PostgreSQL must be running
cp .env.example .env                      # then set DB_USERNAME / DB_PASSWORD

/opt/homebrew/opt/php/bin/php composer install
/opt/homebrew/opt/php/bin/php artisan key:generate
/opt/homebrew/opt/php/bin/php artisan migrate

npm install
```

### Run

```sh
/opt/homebrew/opt/php/bin/php artisan serve     # http://localhost:8000
npm run dev                                     # Vite dev server, separate terminal
```

### Tests, formatting, typecheck

```sh
/opt/homebrew/opt/php/bin/php artisan test
/opt/homebrew/opt/php/bin/php vendor/bin/pint --dirty
npx tsc --noEmit
npm run build
```

## How the code is laid out

The backend is **layered DDD, one folder per domain** under `app/Domains/<Domain>/`, split into a
framework-free domain layer, an application layer of use cases, and an infrastructure layer holding
Eloquent and HTTP. Dependencies point inward only.

Everything is multi-tenant. `Businesses` is the root domain; every other domain carries the business
it belongs to, and route middleware — not the models — is what guarantees isolation.

Identity is two-headed on purpose: every table has an internal auto-increment `id` for joins and a
public `uuid` that is the domain identity and the only one the API ever exposes.

New modules are **generated, never hand-created**:

```sh
/opt/homebrew/opt/php/bin/php artisan make:domain Services \
  --field="name:string" \
  --field="duration_minutes:integer" \
  --field="price_cents:integer"
```

The front end mirrors those domains by name under `resources/js/domains/`. Laravel owns routing and
renders the page; React takes over from there and loads its data from the API — the same endpoints
the native client will use.

## Documentation

**[`CLAUDE.md`](CLAUDE.md) is the reference.** Read it before writing any code: it documents the layer
rules, the uuid/int identity convention, how tenancy is resolved, the `make:domain` generator and its
field DSL, and the front-end conventions. It applies to every AI coding tool, not just Claude —
[`AGENTS.md`](AGENTS.md) points there.

## License

Proprietary. All rights reserved.
