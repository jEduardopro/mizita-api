<?php

declare(strict_types=1);

use Tests\Support\Architecture\DomainLayers;

/*
| The layer dependency table from CLAUDE.md, encoded.
|
| These are the rules a review comment cannot enforce: a domain entity importing
| Illuminate, or one domain importing another, compiles cleanly and leaves every
| unit test green while permanently coupling two things that were meant to be
| separable. Catching it costs a few milliseconds here.
|
| Namespaces are discovered from the filesystem rather than listed, so a domain
| added tomorrow is covered by every rule below without anyone remembering to
| come back and add it.
|
| One namespace per expectation, always. pest-plugin-arch implements
| `not->toUse($dependency)` as "run the positive expectation and require it to
| fail", and the positive one fails at the first target namespace that does *not*
| use the dependency. Hand it a list of namespaces and the rule therefore passes
| unless every single one of them violates it - which is to say it never fails.
| Passing the list to ->with() instead keeps each namespace its own expectation,
| and a failure names the namespace that broke the rule.
*/

/*
|--------------------------------------------------------------------------
| The domain layer
|--------------------------------------------------------------------------
|
| Plain PHP, DateTimeImmutable and same-domain classes. Nothing else.
*/

it('keeps the framework out of the domain layer', function (string $namespace) {
    expect($namespace)->not->toUse(['Illuminate', 'Laravel', 'Inertia', 'Symfony']);
})->with(fn () => DomainLayers::domain());

it('keeps time in the domain layer in DateTimeImmutable, never Carbon', function (string $namespace) {
    expect($namespace)->not->toUse(['Carbon', 'Illuminate\Support\Carbon']);
})->with(fn () => DomainLayers::domain());

it('keeps the domain layer from reaching down into infrastructure', function (string $namespace) {
    expect($namespace)->not->toUse(DomainLayers::infrastructure());
})->with(fn () => DomainLayers::domain());

it('keeps the domain layer from reaching out into the application layer', function (string $namespace) {
    expect($namespace)->not->toUse(DomainLayers::application());
})->with(fn () => DomainLayers::domain());

/*
|--------------------------------------------------------------------------
| The application layer
|--------------------------------------------------------------------------
|
| Its own domain, the shared ports, and framework *interfaces* only. Jobs,
| Commands and Listeners are the one reason a framework base class may appear
| here at all, and they stay thin wrappers around a use case.
*/

it('keeps Eloquent and the facades out of the application layer', function (string $namespace) {
    expect($namespace)->not->toUse(['Illuminate\Database', 'Illuminate\Support\Facades']);
})->with(fn () => DomainLayers::application());

it('never lets the application layer see an HTTP request', function (string $namespace) {
    expect($namespace)->not->toUse(['Illuminate\Http', 'Symfony\Component\HttpFoundation', 'Inertia']);
})->with(fn () => DomainLayers::application());

it('keeps the application layer from reaching down into infrastructure', function (string $namespace) {
    expect($namespace)->not->toUse(DomainLayers::infrastructure());
})->with(fn () => DomainLayers::application());

it('keeps use cases away from the container, the config and the clock', function (string $namespace) {
    // Time and identity arrive as injected ports. A use case that can call
    // now() is a use case whose timestamps cannot be asserted.
    expect($namespace)->not->toUse(['Illuminate\Container', 'Illuminate\Config', 'Illuminate\Foundation']);
})->with(fn () => DomainLayers::application());

/*
|--------------------------------------------------------------------------
| Crossing domains
|--------------------------------------------------------------------------
|
| The rule most easily broken by accident, because a direct import compiles and
| every unit test stays green. A neighbour is reachable only through a port the
| consumer declares, adapted in the consumer's own Infrastructure/Gateways.
*/

it('keeps each domain out of every other domain, except from Infrastructure', function (string $namespace) {
    $domain = explode('\\', $namespace)[2];

    $foreignDomains = array_values(array_filter(
        DomainLayers::domainNames(),
        static fn (string $other): bool => $other !== $domain,
    ));

    expect($namespace)->not->toUse(array_map(
        static fn (string $other): string => 'App\Domains\\'.$other,
        $foreignDomains,
    ));
})->with(fn () => [...DomainLayers::domain(), ...DomainLayers::application()])->skip(
    count(DomainLayers::domainNames()) < 2,
    'There is only one domain, so there is nothing to cross.',
);

it('keeps the authentication model out of every domain but its infrastructure', function (string $namespace) {
    // App\Models\User is deliberately one class, because Fortify and Sanctum
    // resolve it by configuration. Accounts adapts it - but only from
    // Infrastructure, where framework concretions belong.
    expect($namespace)->not->toUse('App\Models');
})->with(fn () => [...DomainLayers::domain(), ...DomainLayers::application()]);

it('keeps spatie/laravel-permission inside infrastructure', function (string $namespace) {
    // Roles are persisted with Spatie, and that is a persistence decision. The
    // domain keeps its own StaffRole vocabulary precisely so the package can be
    // replaced without touching a rule - which is only true while no entity,
    // port or use case has ever heard of it.
    expect($namespace)->not->toUse('Spatie');
})->with(fn () => [...DomainLayers::domain(), ...DomainLayers::application()]);

/*
|--------------------------------------------------------------------------
| The shared kernel
|--------------------------------------------------------------------------
|
| The domain layer is allowed to depend on these, which is only defensible for
| as long as they stay plain interfaces and plain values.
*/

arch('the shared ports are interfaces')
    ->expect('App\Shared\Contracts')
    ->toBeInterfaces();

arch('the shared ports drag no framework into the domain layer')
    ->expect('App\Shared\Contracts')
    ->not->toUse(['Illuminate', 'Carbon']);

arch('the shared value objects drag no framework into the domain layer')
    // Every domain layer may import these, so whatever they reach for is
    // reached by the whole platform's domain code. A Str:: call here would
    // quietly undo the rule above for every entity in the repository.
    ->expect('App\Shared\ValueObjects')
    ->not->toUse(['Illuminate', 'Laravel', 'Carbon']);

it('keeps the phone-parsing library inside the one adapter that may see it', function () {
    // PhoneNumber and PhoneNumberType are imported by every domain that stores a
    // number, and the whole argument for translating the library's type enum in
    // the adapter - rather than on our own enum, where it would read better -
    // rests on this staying true. A single `use libphonenumber\...` in a value
    // object would put Google's metadata in the import graph of every entity in
    // the repository, and nothing else in this file would notice.
    //
    // Written by hand rather than as `not->toUse('libphonenumber')` for a
    // mundane reason: pest-plugin-arch resolves the dependency into a layer by
    // parsing every file in it, and that namespace is a few thousand generated
    // metadata files. The expectation exhausts PHP's memory limit before it can
    // reach a verdict. Matching on the namespace token instead costs a grep,
    // catches any entry point rather than a list someone has to keep current,
    // and covers app/Http too, where the validation rule lives.
    $app = dirname(__DIR__, 2).'/app/';

    $offenders = array_values(array_filter(
        DomainLayers::applicationFiles(),
        static fn (string $file): bool => str_contains((string) file_get_contents($app.$file), 'libphonenumber\\'),
    ));

    expect($offenders)->toBe([
        // The one adapter, and the only place a vendor's numbering plan belongs.
        'Shared/Infrastructure/LibPhoneNumberParser.php',
    ]);
});

/*
|--------------------------------------------------------------------------
| Tenancy: where the business comes from
|--------------------------------------------------------------------------
|
| A tenant-scoped use case reads its business from BusinessContext, never from
| its input. Registering the very first staff member is the one place that
| cannot: the row it writes is what resolves the tenant, so there is no context
| to read yet and businessId arrives as an argument.
|
| Both halves are written down, because an exception nobody has fenced in is
| indistinguishable from a habit.
*/

arch('registers the first owner without a business context, because that row is what resolves the tenant')
    ->expect('App\Domains\Staff\Application\UseCases\RegisterBusinessOwner')
    ->not->toUse('App\Shared\Contracts\BusinessContext');

it('takes the tenant from the business context in every other staff use case', function () {
    // Written by hand rather than as an arch() rule because pest-plugin-arch's
    // ignoring() filters the dependencies an object uses, not the objects
    // themselves, so it cannot express "every class here except this one".
    //
    // Vacuous while RegisterBusinessOwner is the only class in that folder, and
    // deliberately written now rather than later: it is the next use case added
    // there that this rule exists to catch, and by then the reason for the
    // exception will be less obvious than it is today.
    $useCases = glob(dirname(__DIR__, 2).'/app/Domains/Staff/Application/UseCases/*.php') ?: [];

    $withoutContext = array_values(array_filter(
        $useCases,
        static fn (string $file): bool => basename($file) !== 'RegisterBusinessOwner.php'
            && ! str_contains((string) file_get_contents($file), 'App\Shared\Contracts\BusinessContext'),
    ));

    expect(array_map(basename(...), $withoutContext))->toBe([]);
});
