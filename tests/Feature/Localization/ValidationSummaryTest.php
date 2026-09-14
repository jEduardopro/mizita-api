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

/*
| The top-level "message" of a 422 is built by ValidationException::summarize(),
| which appends a translated suffix to the first field error. Its keys are the
| English sentences themselves, and a key with no dot never reaches parseKey() -
| Translator::get() resolves it against the JSON catalogue instead - so they live
| in lang/{es,en}.json rather than in validation.php.
|
| summarize() picks the wording *before* calling choice():
|
|     $pluralized = $count === 1 ? 'error' : 'errors';
|     $translator->choice("(and :count more $pluralized)", $count, [...]);
|
| Singular and plural are therefore two independent keys, not a pipe-separated
| pair, and a regression in one is invisible to a test that only covers the
| other. Both counts are covered below.
|
| Both endpoints need an authenticated caller: POST /api/businesses is onboarding
| and sits behind auth:sanctum, and POST /api/customers additionally needs a
| tenant, which is now a staff membership rather than a column on the user. The
| singular case leaves exactly two fields failing, because a third would count
| into the plural key.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    // Symfony's Request::create() synthesises "Accept-Language: en-us,en;q=0.5" on
    // every test request. SetLocale no longer reads it, but blanking it keeps the
    // baseline free of any header the assertions do not state themselves.
    $this->withHeader('Accept-Language', '');

    // Spatie caches its registry while RefreshDatabase rolls the rows back, so a
    // stale entry would outlive the roles it points at.
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

describe('the singular key', function () {
    beforeEach(function () {
        // Onboarding, so no business is needed - and none exists yet, which is
        // exactly the caller this endpoint serves.
        Sanctum::actingAs(User::factory()->create());
    });

    // Two failing fields: one message in the summary, one counted after it. The
    // industry is the only field sent, and it is valid, so it does not count.
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

    // Three failing fields: one message in the summary, two counted after it - so
    // this also proves :count is interpolated rather than hardcoded.
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
        // Accept-Language is not a source, so the plural key resolved here is the
        // Spanish one no matter what the browser advertises.
        $this->postJson('/api/customers', ['email' => 'not-an-email', 'phone' => 123], ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'El campo nombre es obligatorio. (y 2 errores más)');
    });
});

describe('the empty-bag fallback', function () {
    // summarize() falls back to "The given data was invalid." when the bag holds
    // no string message. No FormRequest can reach that branch, so the exception is
    // built directly - the point is that the third JSON key is wired, not that
    // some endpoint produces it.
    it('translates the fallback used when no field message is available', function (string $locale, string $expected) {
        app()->setLocale($locale);

        expect(ValidationException::withMessages([])->getMessage())->toBe($expected);
    })->with([
        'es' => ['es', 'Los datos proporcionados no son válidos.'],
        'en' => ['en', 'The given data was invalid.'],
    ]);
});
