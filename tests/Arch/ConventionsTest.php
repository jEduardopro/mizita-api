<?php

declare(strict_types=1);

use App\Shared\Contracts\DomainFailure;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\Architecture\DomainLayers;

arch('every class a domain defines is final')
    ->expect('App\Domains')
    ->classes()
    ->toBeFinal()
    ->ignoring(DomainLayers::eloquentModels());

arch('every domain port is an interface')
    ->expect(DomainLayers::namespacesFor('Contracts'))
    ->toBeInterfaces();

arch('every domain file declares strict types')
    ->expect('App\Domains')
    ->toUseStrictTypes();

arch('the shared kernel declares strict types')
    ->expect('App\Shared')
    ->toUseStrictTypes();

it('declares strict types in the application-wide classes too', function () {
    expect(['App\Actions', 'App\Http', 'App\Models', 'App\Providers'])->toUseStrictTypes();
})->todo('blocked: the Laravel skeleton files under app/Actions, app/Http, app/Models and app/Providers predate the convention and still omit declare(strict_types=1)');

it('gives every Eloquent model the mandatory Model suffix', function () {
    $models = array_values(array_filter(
        DomainLayers::applicationClasses(),
        static fn (string $class): bool => is_subclass_of($class, Model::class),
    ));

    $unsuffixed = array_values(array_filter(
        $models,
        static fn (string $class): bool => ! str_ends_with($class, 'Model'),
    ));

    expect($models)->not->toBeEmpty()
        ->and($unsuffixed)->toBe(['App\Models\User']);
});

it('lets every domain exception a request can reach classify itself', function () {
    $exceptions = array_values(array_filter(
        DomainLayers::applicationClasses(),
        static fn (string $class): bool => str_contains($class, '\Exceptions\\')
            && str_starts_with($class, 'App\Domains\\'),
    ));

    $plain = array_values(array_filter(
        $exceptions,
        static fn (string $class): bool => ! is_subclass_of($class, DomainFailure::class),
    ));

    expect($exceptions)->not->toBeEmpty()
        ->and($plain)->toBe([
            'App\Domains\Accounts\Exceptions\TemporaryPasswordTooShort',
            'App\Domains\Industries\Exceptions\IndustryAlreadyActive',
            'App\Domains\Industries\Exceptions\IndustryAlreadyInactive',
            'App\Domains\Industries\Exceptions\InvalidIndustryKey',
        ]);
});

arch('lets every shared exception classify itself the way a domain exception does')
    ->expect('App\Shared\Exceptions')
    ->toImplement(DomainFailure::class);

arch('every shared exception is final')
    ->expect('App\Shared\Exceptions')
    ->classes()
    ->toBeFinal();

it('keeps every Eloquent model inside its domain Eloquent Models namespace', function () {
    $strayModels = array_values(array_filter(
        DomainLayers::applicationClasses(),
        static fn (string $class): bool => is_subclass_of($class, Model::class)
            && $class !== 'App\Models\User'
            && ! str_contains($class, '\Infrastructure\Eloquent\Models\\'),
    ));

    expect($strayModels)->toBe([]);
});
