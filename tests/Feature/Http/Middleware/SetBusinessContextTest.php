<?php

declare(strict_types=1);

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Models\User;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\BusinessMembership;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakeBusinessMembership;

uses(RefreshDatabase::class);

const GUARDED_URI = '/api/testing/business-context';

beforeEach(function () {
    $this->withHeader('Accept-Language', '');

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Route::prefix('api')
        ->middleware(['api', 'auth:sanctum', 'business'])
        ->post('/testing/business-context', fn () => response()->json([
            'business_id' => app(BusinessContext::class)->currentBusinessId(),
            'team_id' => app(PermissionRegistrar::class)->getPermissionsTeamId(),
        ]));
});

function memberOf(BusinessModel $business, User $account, bool $owner = false): StaffMemberModel
{
    $factory = StaffMemberModel::factory();

    return ($owner ? $factory->owner() : $factory)->create([
        'business_id' => $business->id,
        'account_id' => $account->id,
    ]);
}

function backdate(StaffMemberModel $member, string $createdAt): void
{
    StaffMemberModel::query()->whereKey($member->getKey())->update(['created_at' => $createdAt]);
}

describe('a caller who belongs to a business', function () {
    beforeEach(function () {
        $this->seed(AuthorizationSeeder::class);
    });

    it('lets the caller through and binds their business as the context', function () {
        $business = BusinessModel::factory()->create();
        $owner = User::factory()->create();

        memberOf($business, $owner, owner: true);

        Sanctum::actingAs($owner);

        $this->postJson(GUARDED_URI)
            ->assertOk()
            ->assertJsonPath('business_id', $business->uuid);
    });

    it('sets the spatie team id to the business integer key', function () {
        $business = BusinessModel::factory()->create();
        $owner = User::factory()->create();

        memberOf($business, $owner, owner: true);

        Sanctum::actingAs($owner);

        $this->postJson(GUARDED_URI)
            ->assertOk()
            ->assertJsonPath('team_id', $business->id);
    });

    it('binds the business named by the X-Business header', function () {
        $owned = BusinessModel::factory()->create();
        $joined = BusinessModel::factory()->create();
        $account = User::factory()->create();

        memberOf($owned, $account, owner: true);
        memberOf($joined, $account);

        Sanctum::actingAs($account);

        $this->postJson(GUARDED_URI, [], ['X-Business' => $joined->uuid])
            ->assertOk()
            ->assertJsonPath('business_id', $joined->uuid);
    });

    it('prefers the owner membership when no header names a business', function () {
        $joined = BusinessModel::factory()->create();
        $owned = BusinessModel::factory()->create();
        $account = User::factory()->create();

        backdate(memberOf($joined, $account), '2020-01-01 00:00:00');
        backdate(memberOf($owned, $account, owner: true), '2024-01-01 00:00:00');

        Sanctum::actingAs($account);

        $this->postJson(GUARDED_URI)
            ->assertOk()
            ->assertJsonPath('business_id', $owned->uuid);
    });

    it('falls back to the oldest membership when none of them is the owner one', function () {
        $oldest = BusinessModel::factory()->create();
        $newest = BusinessModel::factory()->create();
        $account = User::factory()->create();

        backdate(memberOf($newest, $account), '2024-01-01 00:00:00');
        backdate(memberOf($oldest, $account), '2020-01-01 00:00:00');

        Sanctum::actingAs($account);

        $this->postJson(GUARDED_URI)
            ->assertOk()
            ->assertJsonPath('business_id', $oldest->uuid);
    });

    it('refuses a header naming a business the caller is not a member of', function () {
        $stranger = BusinessModel::factory()->create();
        $business = BusinessModel::factory()->create();
        $account = User::factory()->create();

        memberOf($business, $account, owner: true);

        Sanctum::actingAs($account);

        $this->postJson(GUARDED_URI, [], ['X-Business' => $stranger->uuid])
            ->assertForbidden()
            ->assertJsonPath('code', 'business_not_accessible');
    });

    it('never names the business it refused', function () {
        $stranger = BusinessModel::factory()->create();
        $business = BusinessModel::factory()->create();
        $account = User::factory()->create();

        memberOf($business, $account, owner: true);

        Sanctum::actingAs($account);

        $this->postJson(GUARDED_URI, [], ['X-Business' => $stranger->uuid])
            ->assertForbidden()
            ->assertDontSee($stranger->uuid);
    });
});

it('rejects an authenticated caller who belongs to no business', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson(GUARDED_URI)
        ->assertForbidden()
        ->assertJsonPath('code', 'no_business');
});

it('rejects an unauthenticated caller before it ever looks for a business', function () {
    $this->postJson(GUARDED_URI)
        ->assertUnauthorized();
});

it('leaves no business context bound when it refuses the caller', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson(GUARDED_URI)->assertForbidden();

    expect(app()->bound(BusinessContext::class))->toBeFalse();
});

describe('the language of the refusal', function () {
    beforeEach(function () {
        $this->app->instance(BusinessMembership::class, new FakeBusinessMembership);

        Sanctum::actingAs(User::factory()->create());
    });

    it('answers the 403 in the default locale', function () {
        $this->postJson(GUARDED_URI)
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'Este usuario no pertenece a ningún negocio.',
                'code' => 'no_business',
            ]);
    });

    it('answers the 403 in the language the caller chose', function (array $query, array $headers) {
        $uri = GUARDED_URI.($query === [] ? '' : '?'.http_build_query($query));

        $this->postJson($uri, [], $headers)
            ->assertForbidden()
            ->assertJsonPath('message', 'This user does not belong to a business.')
            ->assertJsonPath('code', 'no_business');
    })->with([
        '?lang=' => [['lang' => 'en'], []],
        'X-Locale' => [[], ['X-Locale' => 'en']],
    ]);

    it('answers the 403 in spanish for an english browser', function () {
        $this->postJson(GUARDED_URI, [], ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Este usuario no pertenece a ningún negocio.');
    });

    it('does not leak the translation key when the caller asks for an unsupported language', function () {
        $this->postJson(GUARDED_URI.'?lang=fr')
            ->assertForbidden()
            ->assertJsonPath('message', 'Este usuario no pertenece a ningún negocio.');
    });
});
