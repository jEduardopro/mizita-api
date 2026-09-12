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
*/

/*
|--------------------------------------------------------------------------
| The domain layer
|--------------------------------------------------------------------------
|
| Plain PHP, DateTimeImmutable and same-domain classes. Nothing else.
*/

arch('the domain layer knows nothing about the framework')
    ->expect(DomainLayers::domain())
    ->not->toUse(['Illuminate', 'Laravel', 'Inertia', 'Symfony']);

arch('the domain layer keeps time in DateTimeImmutable, never Carbon')
    ->expect(DomainLayers::domain())
    ->not->toUse(['Carbon', 'Illuminate\Support\Carbon']);

arch('the domain layer does not reach down into infrastructure')
    ->expect(DomainLayers::domain())
    ->not->toUse(DomainLayers::infrastructure());

arch('the domain layer does not reach out into the application layer')
    ->expect(DomainLayers::domain())
    ->not->toUse(DomainLayers::application());

/*
|--------------------------------------------------------------------------
| The application layer
|--------------------------------------------------------------------------
|
| Its own domain, the shared ports, and framework *interfaces* only. Jobs,
| Commands and Listeners are the one reason a framework base class may appear
| here at all, and they stay thin wrappers around a use case.
*/

arch('the application layer never touches Eloquent or a facade')
    ->expect(DomainLayers::application())
    ->not->toUse(['Illuminate\Database', 'Illuminate\Support\Facades']);

arch('the application layer never sees an HTTP request')
    ->expect(DomainLayers::application())
    ->not->toUse(['Illuminate\Http', 'Symfony\Component\HttpFoundation', 'Inertia']);

arch('the application layer does not reach down into infrastructure')
    ->expect(DomainLayers::application())
    ->not->toUse(DomainLayers::infrastructure());

arch('use cases do not reach for the container, the config or the clock')
    // Time and identity arrive as injected ports. A use case that can call
    // now() is a use case whose timestamps cannot be asserted.
    ->expect(DomainLayers::application())
    ->not->toUse(['Illuminate\Container', 'Illuminate\Config', 'Illuminate\Foundation']);

/*
|--------------------------------------------------------------------------
| Crossing domains
|--------------------------------------------------------------------------
|
| The rule most easily broken by accident, because a direct import compiles and
| every unit test stays green. A neighbour is reachable only through a port the
| consumer declares, adapted in the consumer's own Infrastructure/Gateways.
*/

it('keeps each domain out of every other domain, except from Infrastructure', function (string $domain) {
    $foreignDomains = array_values(array_filter(
        DomainLayers::domainNames(),
        static fn (string $other): bool => $other !== $domain,
    ));

    expect(DomainLayers::insideOf($domain))
        ->not->toUse(array_map(
            static fn (string $other): string => 'App\Domains\\'.$other,
            $foreignDomains,
        ));
})->with(fn () => DomainLayers::domainNames())->skip(
    count(DomainLayers::domainNames()) < 2,
    'There is only one domain, so there is nothing to cross.',
);

arch('no domain imports the authentication model outside Infrastructure')
    // App\Models\User is deliberately one class, because Fortify and Sanctum
    // resolve it by configuration. Accounts adapts it - but only from
    // Infrastructure, where framework concretions belong.
    ->expect([...DomainLayers::domain(), ...DomainLayers::application()])
    ->not->toUse('App\Models');

/*
|--------------------------------------------------------------------------
| The shared kernel
|--------------------------------------------------------------------------
|
| The domain layer is allowed to depend on these, which is only defensible for
| as long as they stay plain interfaces.
*/

arch('the shared ports are interfaces')
    ->expect('App\Shared\Contracts')
    ->toBeInterfaces();

arch('the shared ports drag no framework into the domain layer')
    ->expect('App\Shared\Contracts')
    ->not->toUse(['Illuminate', 'Carbon']);
