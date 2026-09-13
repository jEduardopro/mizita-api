<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/*
| Proves the localization stack is wired end to end, not merely that __() works:
| a real FormRequest fails a real rule and the validator's message bag comes back
| in the resolved language, with the :attribute placeholder translated too.
|
| POST /api/businesses is the subject because it validates several fields with
| translated attribute names and rejects the request before it reaches the
| database. It is onboarding, so it now sits behind auth:sanctum - a request
| with no actor is refused at the guard and never reaches the validator, which
| is why this file authenticates and therefore needs a connection. The caller
| deliberately has no business: that is exactly who this endpoint serves.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    // Symfony's Request::create() synthesises "Accept-Language: en-us,en;q=0.5" on
    // every test request. SetLocale no longer reads it, but blanking it keeps the
    // baseline free of any header the assertions do not state themselves.
    $this->withHeader('Accept-Language', '');

    Sanctum::actingAs(User::factory()->create());
});

it('answers a validation failure in the default locale', function () {
    $this->postJson('/api/businesses', [])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'El campo nombre es obligatorio.')
        ->assertJsonPath('errors.timezone.0', 'El campo zona horaria es obligatorio.');
});

// The top-level "message" of a 422 comes from ValidationException::summarize()
// and resolves against lang/{es,en}.json rather than validation.php. It has its
// own file: tests/Feature/Localization/ValidationSummaryTest.php.

it('answers a validation failure in english when ?lang=en asks for it', function () {
    $this->postJson('/api/businesses?lang=en', [])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'The name field is required.')
        ->assertJsonPath('errors.timezone.0', 'The time zone field is required.');
});

it('answers a validation failure in english when the X-Locale header asks for it', function () {
    $this->postJson('/api/businesses', [], ['X-Locale' => 'en'])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'The name field is required.');
});

it('answers a validation failure in spanish for an english browser', function () {
    // Accept-Language is not a source. A browser that never asked for English in so
    // many words gets the Spanish the product is built around.
    $this->postJson('/api/businesses', [], ['Accept-Language' => 'en-US,en;q=0.9'])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'El campo nombre es obligatorio.');
});

it('answers a validation failure in english when the locale cookie asks for it', function () {
    // withCredentials() is required: Laravel's test client sends no cookies at all
    // on a JSON request without it, which would make this pass for the wrong reason.
    $this->withCredentials()
        ->withUnencryptedCookie('locale', 'en')
        ->postJson('/api/businesses', [])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'The name field is required.');
});

it('translates the attribute name inside a rule that is not required', function () {
    // max:120 exercises a different message *and* proves the translated attribute
    // is not special-cased to the required rule.
    $this->postJson('/api/businesses', [
        'name' => str_repeat('a', 121),
        'timezone' => 'Europe/Madrid',
        'industry_id' => '01930000-0000-7000-8000-0000000000f1',
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'El campo nombre no puede tener más de 120 caracteres.');
});

it('keeps an unsupported language from leaking untranslated keys into the body', function () {
    // ?lang=fr falls through to the default rather than selecting a lang/fr that
    // does not exist, which would make Laravel echo "validation.required" back.
    $this->postJson('/api/businesses?lang=fr', [])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'El campo nombre es obligatorio.');
});
