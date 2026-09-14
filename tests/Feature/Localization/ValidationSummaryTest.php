<?php

declare(strict_types=1);

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withHeader('Accept-Language', '');

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

describe('the singular key', function () {
    beforeEach(function () {
        Sanctum::actingAs(User::factory()->create());
    });

    $onlyIndustry = ['industry_id' => '01930000-0000-7000-8000-0000000000f1'];

    it('translates the summary line of a 422 with exactly two errors', function () use ($onlyIndustry) {
        $this->postJson('/api/businesses', $onlyIndustry)
            ->assertStatus(422)
            ->assertJsonPath('message', 'El campo nombre es obligatorio. (y 1 error más)');
    });

    it('keeps the english summary line intact for two errors', function () use ($onlyIndustry) {
        $this->postJson('/api/businesses?lang=en', $onlyIndustry)
            ->assertStatus(422)
            ->assertJsonPath('message', 'The name field is required. (and 1 more error)');
    });
});

describe('the plural key', function () {
    beforeEach(function () {
        $this->seed(AuthorizationSeeder::class);

        $business = BusinessModel::factory()->create();
        $owner = User::factory()->create();

        StaffMemberModel::factory()->owner()->create([
            'business_id' => $business->id,
            'account_id' => $owner->id,
        ]);

        Sanctum::actingAs($owner);
    });

    it('translates the summary line of a 422 with three errors', function () {
        $this->postJson('/api/customers', ['email' => 'not-an-email', 'phone' => 123])
            ->assertStatus(422)
            ->assertJsonPath('message', 'El campo nombre es obligatorio. (y 2 errores más)');
    });

    it('keeps the english summary line intact for three errors', function () {
        $this->postJson('/api/customers?lang=en', ['email' => 'not-an-email', 'phone' => 123])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The name field is required. (and 2 more errors)');
    });

    it('follows the locale through every source, not just ?lang=', function (array $headers) {
        $this->postJson('/api/customers', ['email' => 'not-an-email', 'phone' => 123], $headers)
            ->assertStatus(422)
            ->assertJsonPath('message', 'The name field is required. (and 2 more errors)');
    })->with([
        'X-Locale' => [['X-Locale' => 'en']],
    ]);

    it('stays in spanish for an english browser', function () {
        $this->postJson('/api/customers', ['email' => 'not-an-email', 'phone' => 123], ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'El campo nombre es obligatorio. (y 2 errores más)');
    });
});

describe('the empty-bag fallback', function () {
    it('translates the fallback used when no field message is available', function (string $locale, string $expected) {
        app()->setLocale($locale);

        expect(ValidationException::withMessages([])->getMessage())->toBe($expected);
    })->with([
        'es' => ['es', 'Los datos proporcionados no son válidos.'],
        'en' => ['en', 'The given data was invalid.'],
    ]);
});
