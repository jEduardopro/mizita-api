<?php

declare(strict_types=1);

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Models\User;
use App\Shared\Contracts\BusinessMembership;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakeBusinessMembership;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withHeader('Accept-Language', '');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('lets a caller who belongs to a business through', function () {
    $this->seed(AuthorizationSeeder::class);

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
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/customers', ['name' => 'Ada Lovelace'])
        ->assertForbidden();
});

it('rejects an unauthenticated caller before it ever looks for a business', function () {
    $this->postJson('/api/customers', ['name' => 'Ada Lovelace'])
        ->assertUnauthorized();
});

describe('the language of the refusal', function () {
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
