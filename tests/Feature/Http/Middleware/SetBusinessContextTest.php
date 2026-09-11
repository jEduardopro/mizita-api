<?php

declare(strict_types=1);

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/*
| The 403 this middleware aborts with is now a translated string rather than a
| literal, so its body follows whatever SetLocale resolved. SetLocale is
| prepended to the api group and "business" is a route-level alias, so SetLocale
| has always run by the time __() is called here.
|
| This file needs a connection: the middleware reads the caller's business
| through the users table.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    // Symfony's Request::create() synthesises "Accept-Language: en-us,en;q=0.5" on
    // every test request; blanking it keeps the default-locale case honest.
    $this->withHeader('Accept-Language', '');
});

it('lets a caller who belongs to a business through', function () {
    $business = BusinessModel::factory()->create();
    Sanctum::actingAs(User::factory()->create(['business_id' => $business->uuid]));

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

it('answers the 403 in the default locale', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/customers', ['name' => 'Ada Lovelace'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Este usuario no pertenece a ningún negocio.');
});

it('answers the 403 in the language the caller negotiated', function (array $query, array $headers) {
    Sanctum::actingAs(User::factory()->create());

    $uri = '/api/customers'.($query === [] ? '' : '?'.http_build_query($query));

    $this->postJson($uri, ['name' => 'Ada Lovelace'], $headers)
        ->assertForbidden()
        ->assertJsonPath('message', 'This user does not belong to a business.');
})->with([
    '?lang=' => [['lang' => 'en'], []],
    'X-Locale' => [[], ['X-Locale' => 'en']],
    'Accept-Language' => [[], ['Accept-Language' => 'en-GB,en;q=0.9']],
]);

it('does not leak the translation key when the caller asks for an unsupported language', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/customers?lang=fr', ['name' => 'Ada Lovelace'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Este usuario no pertenece a ningún negocio.');
});
