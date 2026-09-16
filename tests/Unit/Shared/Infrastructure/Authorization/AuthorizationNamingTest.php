<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Authorization\AuthorizationNaming;

describe('the slug a name derives', function () {
    it('turns both separators a name may carry into the one a slug may', function (string $name, string $slug) {
        expect(AuthorizationNaming::slugFor($name))->toBe($slug);
    })->with([
        'the verb noun convention' => ['create_service', 'create-service'],
        'the dotted convention it replaced' => ['business.manage', 'business-manage'],
        'a name with several underscores' => ['view_service_images', 'view-service-images'],
        'a name mixing both separators' => ['services.view_all', 'services-view-all'],
        'a name with neither' => ['owner', 'owner'],
        'a role name the seeder and the clone must agree on' => ['front_desk', 'front-desk'],
        'a name already hyphenated' => ['front-desk', 'front-desk'],
    ]);

    it('leaves a slug it derived alone when it is handed it back', function (string $name) {
        expect(AuthorizationNaming::slugFor(AuthorizationNaming::slugFor($name)))
            ->toBe(AuthorizationNaming::slugFor($name));
    })->with(['create_service', 'business.manage', 'owner']);

    it('collapses two names that differ only in their separator, which is why a catalogue holds one of them', function () {
        expect(AuthorizationNaming::slugFor('manage_business'))
            ->toBe(AuthorizationNaming::slugFor('manage.business'));
    });

    it('keeps the case it was handed, so a name is never silently rewritten', function () {
        expect(AuthorizationNaming::slugFor('Manage_Business'))->toBe('Manage-Business');
    });
});

describe('the translation key a name derives', function () {
    it('keys the description under the catalogue it belongs to', function (string $namespace, string $name, string $key) {
        expect(AuthorizationNaming::descriptionKeyFor($namespace, $name))->toBe($key);
    })->with([
        'a permission' => ['permissions', 'create_service', 'permissions.create_service.description'],
        'a role' => ['roles', 'owner', 'roles.owner.description'],
    ]);

    it('keys the description by the name and never by the slug, so the lookup matches the flat locale file', function () {
        expect(AuthorizationNaming::descriptionKeyFor('permissions', 'manage_business'))
            ->toBe('permissions.manage_business.description')
            ->not->toContain('manage-business');
    });
});
