<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Permissions\SeededStaffRole;
use App\Shared\ValueObjects\AuthorizationScope;
use PHPUnit\Framework\Assert;

/**
 * @return array<string, array<string, array<string, mixed>>>
 */
function mizitaAuthorizationCatalogue(): array
{
    return require dirname(__DIR__, 3).'/config/authorization.php';
}

/**
 * @return array<string, array<string, mixed>>
 */
function mizitaCatalogueSection(string $section): array
{
    return mizitaAuthorizationCatalogue()[$section];
}

/**
 * @return array<string, string>
 */
function mizitaDomainForModule(): array
{
    return [
        'business' => 'Businesses',
        'staff' => 'Staff',
        'services' => 'Services',
    ];
}

/**
 * @param  array<string, mixed>  $permission
 */
function mizitaModuleOf(array $permission): string
{
    return (string) $permission['module'];
}

/**
 * @param  array<string, mixed>  $role
 * @return list<string>
 */
function mizitaGrantedPermissions(array $role): array
{
    $granted = $role['permissions'];

    if ($granted !== '*') {
        return array_values($granted);
    }

    return array_keys(array_filter(
        mizitaCatalogueSection('permissions'),
        static fn (array $permission): bool => $permission['scope'] === $role['scope'],
    ));
}

/**
 * @param  array<array-key, mixed>  $translations
 * @return list<string>
 */
function mizitaLeafKeys(array $translations, string $prefix = ''): array
{
    $keys = [];

    foreach ($translations as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

        if (is_array($value)) {
            $keys = [...$keys, ...mizitaLeafKeys($value, $path)];

            continue;
        }

        $keys[] = $path;
    }

    sort($keys);

    return $keys;
}

/**
 * @return array<array-key, mixed>
 */
function mizitaLocaleFile(string $locale, string $catalogue): array
{
    return require dirname(__DIR__, 3)."/lang/{$locale}/{$catalogue}.php";
}

describe('the shape of an entry', function () {
    it('gives every permission a scope the schema allows', function () {
        foreach (mizitaCatalogueSection('permissions') as $name => $permission) {
            expect($permission['scope'])->toBeInstanceOf(AuthorizationScope::class, "permission [{$name}]");
        }
    });

    it('gives every permission a module to be grouped under', function () {
        foreach (mizitaCatalogueSection('permissions') as $name => $permission) {
            expect($permission['module'] ?? '')->toBeString()->not->toBe('', "permission [{$name}]");
        }
    });

    it('gives every role a scope, a template flag and a permission list', function () {
        foreach (mizitaCatalogueSection('roles') as $name => $role) {
            expect($role['scope'])->toBeInstanceOf(AuthorizationScope::class, "role [{$name}]")
                ->and($role['template'])->toBeBool("role [{$name}]")
                ->and($role['permissions'] === '*' || is_array($role['permissions']))
                ->toBeTrue("role [{$name}] grants neither a list nor '*'");
        }
    });

    it('leaves every derived slug unique, because the database says they are', function (string $section) {
        $slugs = array_map(
            static fn (string $name): string => str_replace(['.', '_'], '-', $name),
            array_keys(mizitaCatalogueSection($section)),
        );

        expect($slugs)->toBe(array_values(array_unique($slugs)));
    })->with(['permissions', 'roles']);
});

describe('what a role is allowed to grant', function () {
    it('names only permissions the catalogue defines', function () {
        $defined = array_keys(mizitaCatalogueSection('permissions'));

        foreach (mizitaCatalogueSection('roles') as $name => $role) {
            $unknown = array_values(array_diff(mizitaGrantedPermissions($role), $defined));

            Assert::assertSame([], $unknown, "role [{$name}] grants permissions nothing defines");
        }
    });

    it('never lets a grant cross a scope', function () {
        $permissions = mizitaCatalogueSection('permissions');

        foreach (mizitaCatalogueSection('roles') as $name => $role) {
            foreach (mizitaGrantedPermissions($role) as $granted) {
                expect($permissions[$granted]['scope'])->toBe(
                    $role['scope'],
                    "role [{$name}] grants [{$granted}], which belongs to another scope",
                );
            }
        }
    });

    it('expands the wildcard into something rather than nothing', function () {
        foreach (mizitaCatalogueSection('roles') as $name => $role) {
            if ($role['permissions'] !== '*') {
                continue;
            }

            expect(mizitaGrantedPermissions($role))->not->toBeEmpty("role [{$name}]");
        }
    });
});

describe('the owner role', function () {
    it('is never a template', function () {
        expect(mizitaCatalogueSection('roles')['owner']['template'])->toBeFalse();
    });

    it('pins the primary key the single-owner index names literally', function () {
        expect(mizitaCatalogueSection('roles')['owner']['id'])->toBe(SeededStaffRole::OWNER_ID);
    });

    it('holds every permission in its own scope', function () {
        expect(mizitaCatalogueSection('roles')['owner']['permissions'])->toBe('*');
    });
});

describe('the staff role', function () {
    it('exists only as a per-business clone', function () {
        expect(mizitaCatalogueSection('roles')['staff']['template'])->toBeTrue();
    });

    it('grants exactly what the catalogue declares, which is all a re-seed can ever put on a clone', function () {
        expect(mizitaCatalogueSection('roles')['staff']['permissions'])->toBe(['view_services']);
    });
});

describe('the honesty rule', function () {
    it('names a module this repository has a domain for', function () {
        $mapped = array_keys(mizitaDomainForModule());

        foreach (mizitaCatalogueSection('permissions') as $name => $permission) {
            Assert::assertContains(
                mizitaModuleOf($permission),
                $mapped,
                "permission [{$name}] is grouped under a module no domain is mapped for",
            );
        }
    });

    it('maps every module onto a directory that is really there', function () {
        foreach (mizitaDomainForModule() as $module => $domain) {
            expect(is_dir(dirname(__DIR__, 3).'/app/Domains/'.$domain))
                ->toBeTrue("module [{$module}] claims the domain [{$domain}], which does not exist");
        }
    });
});

describe('the labels the catalogue promises', function () {
    it('resolves every entry in every locale', function (string $section) {
        $expected = [];

        foreach (array_keys(mizitaCatalogueSection($section)) as $name) {
            $expected[] = $name.'.label';
            $expected[] = $name.'.description';
        }

        sort($expected);

        foreach (['en', 'es'] as $locale) {
            Assert::assertSame(
                $expected,
                mizitaLeafKeys(mizitaLocaleFile($locale, $section)),
                "lang/{$locale}/{$section}.php does not match the catalogue",
            );
        }
    })->with(['permissions', 'roles']);
});
