<?php

declare(strict_types=1);

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

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
| The plural case needs a third failing field: POST /api/businesses validates two
| fields and so can never produce more than two messages. POST /api/customers has
| three, which is why this file needs a connection and an authenticated caller.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    // Symfony's Request::create() synthesises "Accept-Language: en-us,en;q=0.5" on
    // every test request. SetLocale no longer reads it, but blanking it keeps the
    // baseline free of any header the assertions do not state themselves.
    $this->withHeader('Accept-Language', '');
});

describe('the singular key', function () {
    // Two failing fields: one message in the summary, one counted after it.
    it('translates the summary line of a 422 with exactly two errors', function () {
        $this->postJson('/api/businesses', [])
            ->assertStatus(422)
            ->assertJsonPath('message', 'El campo nombre es obligatorio. (y 1 error más)');
    });

    it('keeps the english summary line intact for two errors', function () {
        $this->postJson('/api/businesses?lang=en', [])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The name field is required. (and 1 more error)');
    });
});

describe('the plural key', function () {
    beforeEach(function () {
        $business = BusinessModel::factory()->create();
        Sanctum::actingAs(User::factory()->create(['business_id' => $business->uuid]));
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
