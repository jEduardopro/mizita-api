<?php

declare(strict_types=1);

use Tests\Support\Architecture\DomainLayers;

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
    expect($namespace)->not->toUse(['Illuminate\Container', 'Illuminate\Config', 'Illuminate\Foundation']);
})->with(fn () => DomainLayers::application());

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
    expect($namespace)->not->toUse('App\Models');
})->with(fn () => [...DomainLayers::domain(), ...DomainLayers::application()]);

it('keeps spatie/laravel-permission inside infrastructure', function (string $namespace) {
    expect($namespace)->not->toUse('Spatie');
})->with(fn () => [...DomainLayers::domain(), ...DomainLayers::application()]);

arch('the shared ports are interfaces')
    ->expect('App\Shared\Contracts')
    ->toBeInterfaces();

arch('the shared ports drag no framework into the domain layer')
    ->expect('App\Shared\Contracts')
    ->not->toUse(['Illuminate', 'Carbon']);

arch('the shared value objects drag no framework into the domain layer')
    ->expect('App\Shared\ValueObjects')
    ->not->toUse(['Illuminate', 'Laravel', 'Carbon']);

it('keeps the phone-parsing library inside the one adapter that may see it', function () {
    $app = dirname(__DIR__, 2).'/app/';

    $offenders = array_values(array_filter(
        DomainLayers::applicationFiles(),
        static fn (string $file): bool => str_contains((string) file_get_contents($app.$file), 'libphonenumber\\'),
    ));

    expect($offenders)->toBe([
        'Shared/Infrastructure/LibPhoneNumberParser.php',
    ]);
});

arch('registers the first owner without a business context, because that row is what resolves the tenant')
    ->expect('App\Domains\Staff\Application\UseCases\RegisterBusinessOwner')
    ->not->toUse('App\Shared\Contracts\BusinessContext');

it('takes the tenant from the business context in every other staff use case', function () {
    $useCases = glob(dirname(__DIR__, 2).'/app/Domains/Staff/Application/UseCases/*.php') ?: [];

    $withoutContext = array_values(array_filter(
        $useCases,
        static fn (string $file): bool => basename($file) !== 'RegisterBusinessOwner.php'
            && ! str_contains((string) file_get_contents($file), 'App\Shared\Contracts\BusinessContext'),
    ));

    expect(array_map(basename(...), $withoutContext))->toBe([]);
});
