<?php

declare(strict_types=1);

use App\Shared\Contracts\DomainFailure;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\Architecture\DomainLayers;

/*
| The coding conventions from CLAUDE.md that a machine can check: strict types
| in the project's own code, final by default, and the mandatory Model suffix
| that keeps an Eloquent row distinguishable at a glance from the entity it
| persists.
*/

arch('every class a domain defines is final')
    // Eloquent models are the documented exception: the framework extends and
    // proxies them, and Laravel's own conventions leave them open.
    ->expect('App\Domains')
    ->classes()
    ->toBeFinal()
    ->ignoring(DomainLayers::eloquentModels());

arch('every domain port is an interface')
    // A port is a seam, and a seam an adapter cannot be written against is not
    // one. A concrete class or an abstract base landing in Contracts/ compiles,
    // binds and passes every unit test that happens to use the real thing - and
    // takes the "constructible with mocks alone" bar down with it.
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
    // An entity and the row that persists it are different things, and the
    // suffix is what stops the two being confused in an import list.
    $models = array_values(array_filter(
        DomainLayers::applicationClasses(),
        static fn (string $class): bool => is_subclass_of($class, Model::class),
    ));

    $unsuffixed = array_values(array_filter(
        $models,
        static fn (string $class): bool => ! str_ends_with($class, 'Model'),
    ));

    expect($models)->not->toBeEmpty()
        // App\Models\User is the documented divergence: users stays one class
        // because Fortify and Sanctum resolve it by configuration.
        ->and($unsuffixed)->toBe(['App\Models\User']);
});

it('lets every domain exception a request can reach classify itself', function () {
    // A DomainFailure carries its own error code and kind, which is what lets
    // the HTTP edge render it as the right status without a central match over
    // exception class names. An exception that forgets the interface is a 500
    // with a leaked message, and nothing else fails first.
    $exceptions = array_values(array_filter(
        DomainLayers::applicationClasses(),
        static fn (string $class): bool => str_contains($class, '\Exceptions\\')
            && str_starts_with($class, 'App\Domains\\'),
    ));

    $plain = array_values(array_filter(
        $exceptions,
        static fn (string $class): bool => ! is_subclass_of($class, DomainFailure::class),
    ));

    // The exemptions are the catalog's own rules. Industries is seeded, has no
    // write endpoint, and its key is authored rather than submitted: all three
    // of these can only fire from a seeder or a console command, where they are
    // a programming error rather than something to show a caller a sentence
    // about. Anything else appearing in this list is a missing interface.
    expect($exceptions)->not->toBeEmpty()
        ->and($plain)->toBe([
            'App\Domains\Industries\Exceptions\IndustryAlreadyActive',
            'App\Domains\Industries\Exceptions\IndustryAlreadyInactive',
            'App\Domains\Industries\Exceptions\InvalidIndustryKey',
        ]);
});

it('keeps every Eloquent model inside its domain Eloquent Models namespace', function () {
    $strayModels = array_values(array_filter(
        DomainLayers::applicationClasses(),
        static fn (string $class): bool => is_subclass_of($class, Model::class)
            && $class !== 'App\Models\User'
            && ! str_contains($class, '\Infrastructure\Eloquent\Models\\'),
    ));

    expect($strayModels)->toBe([]);
});
