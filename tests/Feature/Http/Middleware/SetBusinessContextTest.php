<?php

declare(strict_types=1);

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Models\User;
use App\Shared\Contracts\BusinessMembership;
use Database\Seeders\StaffRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakeBusinessMembership;

/*
| The 403 this middleware aborts with is a translated string rather than a
| literal, so its body follows whatever SetLocale resolved. SetLocale is
| prepended to the api group and "business" is a route-level alias, so SetLocale
| has always run by the time __() is called here.
|
| This file needs a connection: the tenant is resolved from the caller's staff
| membership, which is a row - users.business_id is gone, and a user column was
| never able to express a person who works at two businesses anyway.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    // Symfony's Request::create() synthesises "Accept-Language: en-us,en;q=0.5" on
    // every test request. SetLocale no longer reads it, but blanking it keeps the
    // baseline free of any header the assertions do not state themselves.
    $this->withHeader('Accept-Language', '');

    // Spatie serves its registry from cache, and RefreshDatabase rolls the rows
    // back between tests: without this, a later test reads roles that no longer
    // exist and every assignment fails with RoleDoesNotExist.
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('lets a caller who belongs to a business through', function () {
    // The whole chain, unfaked: a membership row is what makes this account a
    // business user, and the role assignment it carries is what the tenant
    // resolver orders by - so the roles have to be seeded first.
    $this->seed(StaffRoleSeeder::class);

    $business = BusinessModel::factory()->create();
    $owner = User::factory()->create();

    StaffMemberModel::factory()->owner()->create([
        'business_id' => $business->id,
        'account_id' => $owner->id,
    ]);

    Sanctum::actingAs($owner);

    $this->postJson('/api/customers', ['name' => 'Ada Lovelace'])
        ->assertCreated();
});

it('rejects an authenticated caller who belongs to no business', function () {
    // No membership row at all, asked of the real resolver: a plain account is
    // an end customer, not a business user.
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/customers', ['name' => 'Ada Lovelace'])
        ->assertForbidden();
});

it('rejects an unauthenticated caller before it ever looks for a business', function () {
    $this->postJson('/api/customers', ['name' => 'Ada Lovelace'])
        ->assertUnauthorized();
});

describe('the language of the refusal', function () {
    /*
    | These are about the message, not about how membership resolves, so the
    | precondition is stated through the port instead of arranged as the absence
    | of rows. The test above is the one that proves the real resolver agrees.
    */
    beforeEach(function () {
        $this->app->instance(BusinessMembership::class, new FakeBusinessMembership);

        Sanctum::actingAs(User::factory()->create());
    });

    it('answers the 403 in the default locale', function () {
        $this->postJson('/api/customers', ['name' => 'Ada Lovelace'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Este usuario no pertenece a ningún negocio.');
    });

    it('answers the 403 in the language the caller chose', function (array $query, array $headers) {
        $uri = '/api/customers'.($query === [] ? '' : '?'.http_build_query($query));

        $this->postJson($uri, ['name' => 'Ada Lovelace'], $headers)
            ->assertForbidden()
            ->assertJsonPath('message', 'This user does not belong to a business.');
    })->with([
        '?lang=' => [['lang' => 'en'], []],
        'X-Locale' => [[], ['X-Locale' => 'en']],
    ]);

    it('answers the 403 in spanish for an english browser', function () {
        // Accept-Language is not a source; only an explicit choice changes the language.
        $this->postJson('/api/customers', ['name' => 'Ada Lovelace'], ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Este usuario no pertenece a ningún negocio.');
    });

    it('does not leak the translation key when the caller asks for an unsupported language', function () {
        $this->postJson('/api/customers?lang=fr', ['name' => 'Ada Lovelace'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Este usuario no pertenece a ningún negocio.');
    });
});
