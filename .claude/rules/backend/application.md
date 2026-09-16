---
paths:
  - "app/**/*.php"
  - "database/**/*.php"
  - "routes/**/*.php"
  - "config/**/*.php"
  - "tests/**/*.php"
---

# Application layer

## Use cases

`final class`, one public `handle(InputDto): UseCaseResponse<OutputDto>`. Constructor takes only interfaces. Inside a use case, no Eloquent, no facades, no `now()`, `config()`, `app()`, `Str::uuid()`, no `Illuminate\Http\*`.

The bar: `new OnboardBusiness(...)` must be constructible with mocks alone — no container, no migrations, no database.

**A use case never throws a `DomainFailure` at its caller; it returns one.** `handle()` opens with a `try` whose first statement is `$input->validate();`, and its `catch (DomainFailure $failure)` returns `UseCaseResponse::failure($failure)`. Anything that is *not* a `DomainFailure` still escapes — those are programmer errors, and the controller's `catch (Throwable)` logs them as a 500.

**The `try` closes before any post-commit work.** `OnboardBusiness` and `AuthenticateWithGoogle` dispatch their events after `TransactionManager::run()` returns, and that loop must stay outside the `catch`: a listener throwing after the commit would otherwise produce a failure response for a row that exists, which is a lie the client cannot detect. This is why the body is not simply wrapped in a helper that takes a closure.

Only catch where a `DomainFailure` can actually be thrown — `AttachPhone` and `ListIndustries` have no catch at all, because `Phones` has no `Exceptions/` and no Industries failure is reachable from `allActive()`. A catch that cannot fire is dead code.


## Shared kernel

`app/Shared/Contracts/` holds the cross-domain ports, bound in `app/Providers/AppServiceProvider.php`:

- `Clock::now(): DateTimeImmutable` → `SystemClock`. Injecting time makes timestamps assertable and date rules testable.
- `IdGenerator::next(): string` → `UuidGenerator` (`Str::uuid7()`, time-ordered). Lets an entity carry its identity before it is persisted.
- `TransactionManager::run(callable): mixed` → `EloquentTransactionManager`. Keeps `DB::transaction()` out of use cases.

Inject them; do not recreate them. Add a new shared port only when two or more domains need it — or when the consumer is the HTTP kernel, which is not a domain and has no `Contracts/` of its own: `BusinessMembership` and `BusinessTeamKey` are there for that reason, each implemented by the domain that owns the data.

`app/Shared/Application/` holds the one thing every use case returns:

- `UseCaseResponse<TData>` — `success()` / `failure()`, plus `invalid()`, `conflict()`, `notFound()`, `unauthenticated()` and `forbidden()`, one per `DomainFailureKind` case. Named after the kind and never after HTTP, so status codes stay at the edge.
- `UseCaseError` — the code, the kind, and the **original** `Throwable` when there was one.
- `Warning` — a `messages.warnings` key. A warning carries no message, for the same reason an error does not.

`value()` is the only way to read the data, and it **rethrows** on a failed response. There is no public `$data` property, and it must never be softened to return `null`: this project has no static analyser, so that throw is the only thing standing between a forgotten check and a committed half-write.

**A `UseCaseResponse` never crosses a port.** `PhoneBook::attachToBusiness(): void` and `OwnerRegistrar::registerOwner(): array` are ports whose protocol is "throw", and both are called from inside `OnboardBusiness`'s transaction. A gateway that returned a failure instead of throwing would let `DB::transaction()` see a normal return and commit a business with no owner. The gateway calls `value()`, and that translation is the gateway's whole job.

**Domain exceptions carry their own HTTP classification.** `Exceptions/` may not import `Illuminate`, so the mapping cannot be a `render()` method on the exception; and a central `match` over class names in `bootstrap/app.php` is the growing switch this project forbids. Instead an exception implements `App\Shared\Contracts\DomainFailure`, returning a stable `errorCode()` — which is also its key under `messages.errors` — and a `DomainFailureKind`. `App\Http\Responses\FailurePayload` turns the kind into 422/409/404/403/401 and the code into a translated message. A new exception needs no wiring, only the two methods and a key in **both** locale files.

Two things consume that payload. `App\Http\Responses\ApiResponder` and `WebResponder` render a `UseCaseResponse` the controller already has in hand — the controller picks the one matching its transport, because a single responder branching on request headers would be the growing switch this project forbids. **The split is API vs Web, and only that.** Rendering one wire format is one responsibility, so each side is one class with a method per outcome — `success()`, `failure()`, `unexpected()` — never one class per outcome. `WebResponder` carries no `success()` yet only because a web success is an `Inertia::render` in `routes/web.php` and never reaches a controller; when one does, it gains the method rather than a sibling class. `App\Http\Exceptions\RenderDomainFailure`, registered once against the interface, stays as the net for everything that never reaches a controller: middleware, queued jobs, the console.

`success()` exists for one reason: warnings ride on a **success**, so without it every controller hand-rolls the same envelope and the ones that forget drop warnings silently on the floor. It takes the response, the Resource and the status — the status is controller knowledge and is never guessed. `WarningEnvelope` owns the `{code, message}` shape and the `messages.warnings` prefix for both halves, and returns `[]` rather than `['warnings' => []]`, which is what keeps the key off every body that has nothing to say.

The message on the wire is always the translation, never `getMessage()`: those are English developer strings that interpolate identifiers.

**A `DomainFailure` is never logged.** `bootstrap/app.php` declares `dontReport(DomainFailure::class)`, and that is a decision, not an oversight: a refusal is the *designed* outcome of a rule the user tripped, it already reached the client with a stable `code`, and its stack trace describes the same path from controller to guard clause every time. Logging it buys nothing and costs the signal-to-noise of the file where a real incident has to be visible. The consequence to know: a refusal escaping a **queued job** is unlogged too — it still lands in `failed_jobs`, but it reaches no error channel. If refusals need watching, that is a metric, not `LOG_CHANNEL`.

Everything else goes through `App\Http\Logging\LogUnexpectedFailure`, exactly once, at error level. It branches on `runningInConsole()` because `app(Request::class)` in a worker resolves a *synthetic* request — so console context omits the request fields rather than recording `path: "/"` as though it meant something. `FailureLogContext` stays the one place deciding what is safe to log, and what it must never carry is the request body, headers or email: `POST /api/auth/google` receives an `id_token`, which is a bearer credential.

