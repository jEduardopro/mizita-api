<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Permissions\SeededStaffRole;
use App\Shared\ValueObjects\AuthorizationScope;
use PHPUnit\Framework\Assert;

/*
| config/authorization.php is the whole access model, and the seeder and the
| template cloner both read it rather than deciding anything themselves. That
| makes every mistake in it a mistake in the database: a role granted a
| permission nobody defined, a permission whose locale key resolves to itself,
| an owner role marked as a template.
|
| Pure PHP over a require, so none of this needs a container, a connection or a
| seeded row. The file is plain data and an enum; requiring it costs nothing.
*/

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
 * Which domain directory owns each permission module.
 *
 * Written down rather than derived: a permission module is a lowercase word and
 * a domain directory is a StudlyCase plural, and no single transformation covers
 * both "business" -> "Businesses" and "staff" -> "Staff" - Str::plural('staff')
 * returns 'staff', so ucfirst of it happens to work while ucfirst of the plural
 * of 'business' does not agree with anything reliable. Guessing would make this
 * rule fail on a correct entry, which is worse than an entry here.
 *
 * Adding a permission for a domain that does not exist therefore takes two
 * deliberate acts: a line here, and the directory it names.
 *
 * @return array<string, string>
 */
function mizitaDomainForModule(): array
{
    return [
        'business' => 'Businesses',
        'staff' => 'Staff',
    ];
}

function mizitaModuleOf(string $permissionName): string
{
    return explode('.', $permissionName)[0];
}

/**
 * The permissions '*' stands for, expanded exactly as AuthorizationSeeder does.
 *
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
        // Not persisted, deliberately: which checkboxes sit together on the roles
        // screen must never become a migration. That only holds while every
        // permission has one.
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
        // permissions (slug, guard_name) is unique and roles mirror it per
        // business. The slug is the name with dots turned into dashes, so
        // "staff.manage" and a future "staff-manage" would collide on insert
        // rather than here.
        $slugs = array_map(
            static fn (string $name): string => str_replace('.', '-', $name),
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
        // Where "a business owner must never hold a platform permission" lives.
        // Today no platform permission exists, so this is the rule that has to be
        // standing before the first one lands rather than after.
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
        // A role written as '*' that resolves to an empty list is a role with no
        // permissions at all, which is not what anybody meant by it.
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
        // A security invariant, not a style choice: ownsAnyBusiness() matches
        // roles.name = 'owner' across every team, so a cloned owner would let one
        // account quietly come to own two businesses.
        expect(mizitaCatalogueSection('roles')['owner']['template'])->toBeFalse();
    });

    it('pins the primary key the single-owner index names literally', function () {
        // model_has_roles_single_owner_unique cannot look a name up, so it writes
        // role_id = 1. The two agree on one number or the index protects nothing.
        expect(mizitaCatalogueSection('roles')['owner']['id'])->toBe(SeededStaffRole::OWNER_ID);
    });

    it('holds every permission in its own scope', function () {
        expect(mizitaCatalogueSection('roles')['owner']['permissions'])->toBe('*');
    });
});

describe('the staff role', function () {
    it('exists only as a per-business clone', function () {
        // The one line that keeps Role::findByParam() unambiguous: it matches
        // "business_id is null or business_id = <team>" and returns first() with
        // no order, so a global row sharing a template name makes role assignment
        // attach an undefined one of the two.
        expect(mizitaCatalogueSection('roles')['staff']['template'])->toBeTrue();
    });

    it('starts with nothing, so re-seeding cannot restore what an owner revoked', function () {
        expect(mizitaCatalogueSection('roles')['staff']['permissions'])->toBe([]);
    });
});

describe('the honesty rule', function () {
    it('names a module this repository has a domain for', function () {
        // What mechanically stops services.create landing before Services does.
        $modules = array_unique(array_map(
            mizitaModuleOf(...),
            array_keys(mizitaCatalogueSection('permissions')),
        ));

        $unmapped = array_values(array_diff($modules, array_keys(mizitaDomainForModule())));

        Assert::assertSame([], $unmapped, 'no domain is mapped for these permission modules');
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
        // The description column stores a translation key and there is no foreign
        // key from a database string to a locale file, so a missing entry surfaces
        // as the key itself echoed back at the caller.
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
