<?php

declare(strict_types=1);

use App\Domains\Businesses\ValueObjects\Slug;
use App\Shared\Contracts\BusinessContext;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;
use Tests\Support\Architecture\DomainLayers;

/**
 * @return list<class-string>
 */
function mizitaGateways(): array
{
    return array_values(array_filter(
        DomainLayers::applicationClasses(),
        static fn (string $class): bool => str_contains($class, '\Infrastructure\Gateways\\'),
    ));
}

/**
 * @return list<class-string>
 */
function mizitaPublicCatalogClasses(): array
{
    return array_values(array_filter(
        DomainLayers::applicationClasses(),
        static fn (string $class): bool => str_starts_with($class, 'App\Domains\PublicCatalog\\'),
    ));
}

/**
 * @return list<string>
 */
function mizitaWebRouteSegments(): array
{
    $web = (string) file_get_contents(dirname(__DIR__, 2).'/routes/web.php');

    preg_match_all("#Route::[a-zA-Z]+\(\s*'/([a-z0-9-]+)#", $web, $matches);

    return array_values(array_unique($matches[1]));
}

it('adapts every neighbour through a port its own domain declares', function () {
    $gateways = mizitaGateways();
    $orphans = [];

    foreach ($gateways as $gateway) {
        $domain = explode('\\', $gateway)[2];
        $ports = array_filter(
            class_implements($gateway) ?: [],
            static fn (string $port): bool => str_starts_with($port, 'App\Domains\\'.$domain.'\Contracts\\')
                || str_starts_with($port, 'App\Shared\Contracts\\'),
        );

        if ($ports === []) {
            $orphans[] = $gateway;
        }
    }

    Assert::assertNotEmpty($gateways, 'No gateway was found, so this rule is not checking anything.');

    Assert::assertSame(
        [],
        $orphans,
        "A gateway exists to satisfy a port the consuming domain owns, or one of the shared kernel, so a neighbour's shape never leaks across the boundary:\n  - "
        .implode("\n  - ", $orphans),
    );
});

it('keeps the business context out of the public catalog, which resolves its tenant from a url segment', function () {
    $offenders = array_values(array_filter(
        DomainLayers::applicationFiles(),
        static fn (string $file): bool => str_starts_with($file, 'Domains/PublicCatalog/')
            && str_contains((string) file_get_contents(dirname(__DIR__, 2).'/app/'.$file), BusinessContext::class),
    ));

    expect(mizitaPublicCatalogClasses())->not->toBeEmpty()
        ->and($offenders)->toBe([]);
});

it('declares only reads on the ports the public catalog owns, so an anonymous visit writes nothing', function () {
    $writeVerbs = ['save', 'store', 'create', 'update', 'delete', 'remove', 'replace', 'attach', 'provision', 'sync'];
    $ports = DomainLayers::namespacesFor('Contracts');
    $offenders = [];

    foreach (glob(dirname(__DIR__, 2).'/app/Domains/PublicCatalog/Contracts/*.php') ?: [] as $file) {
        $port = 'App\Domains\PublicCatalog\Contracts\\'.basename($file, '.php');

        foreach ((new ReflectionClass($port))->getMethods() as $method) {
            $writes = array_filter(
                $writeVerbs,
                static fn (string $verb): bool => str_starts_with(strtolower($method->getName()), $verb),
            );

            if ($writes !== [] || (string) $method->getReturnType() === 'void') {
                $offenders[] = $port.'::'.$method->getName().'()';
            }
        }
    }

    expect($ports)->toContain('App\Domains\PublicCatalog\Contracts')
        ->and($offenders)->toBe([]);
});

it('owns no table, so it declares no model and no repository of its own', function () {
    $persistence = array_values(array_filter(
        mizitaPublicCatalogClasses(),
        static fn (string $class): bool => is_subclass_of($class, Model::class)
            || str_contains($class, '\Infrastructure\Eloquent\\')
            || str_ends_with($class, 'Repository'),
    ));

    expect($persistence)->toBe([]);
});

it('reserves every url segment the application itself answers to', function () {
    $segments = mizitaWebRouteSegments();
    $reserved = (new ReflectionClass(Slug::class))->getConstant('RESERVED');

    $claimable = array_values(array_filter(
        $segments,
        static fn (string $segment): bool => ! in_array($segment, $reserved, true),
    ));

    Assert::assertNotEmpty($segments, 'No route segment was read out of routes/web.php, so this rule is not checking anything.');

    Assert::assertSame(
        [],
        $claimable,
        "A business page lives at the site root, so any segment the application answers to must be unclaimable as a slug:\n  - "
        .implode("\n  - ", $claimable),
    );
});
