<?php

declare(strict_types=1);

/*
| Proves the localization stack is wired end to end, not merely that __() works:
| a real FormRequest fails a real rule and the validator's message bag comes back
| in the negotiated language, with the :attribute placeholder translated too.
|
| POST /api/businesses is deliberately the subject: it sits on the plain "api"
| middleware group with no auth and no tenant, and the request never reaches the
| database because validation rejects it first - so this file needs no connection.
*/

beforeEach(function () {
    // Symfony's Request::create() synthesises "Accept-Language: en-us,en;q=0.5" on
    // every test request. Blanking it is what makes "answers in Spanish by default"
    // an assertion about config, rather than about that synthetic header.
    $this->withHeader('Accept-Language', '');
});

it('answers a validation failure in the default locale', function () {
    $this->postJson('/api/businesses', [])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'El campo nombre es obligatorio.')
        ->assertJsonPath('errors.slug.0', 'El campo identificador es obligatorio.');
});

// The top-level "message" of a 422 comes from ValidationException::summarize()
// and resolves against lang/{es,en}.json rather than validation.php. It has its
// own file: tests/Feature/Localization/ValidationSummaryTest.php.

it('answers a validation failure in english when ?lang=en asks for it', function () {
    $this->postJson('/api/businesses?lang=en', [])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'The name field is required.')
        ->assertJsonPath('errors.slug.0', 'The slug field is required.');
});

it('answers a validation failure in english when the X-Locale header asks for it', function () {
    $this->postJson('/api/businesses', [], ['X-Locale' => 'en'])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'The name field is required.');
});

it('answers a validation failure in english when Accept-Language asks for it', function () {
    $this->postJson('/api/businesses', [], ['Accept-Language' => 'en-GB,en;q=0.9'])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'The name field is required.');
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
    // max:255 exercises a different message *and* proves the translated attribute
    // is not special-cased to the required rule.
    $this->postJson('/api/businesses', ['name' => str_repeat('a', 256), 'slug' => 'ok'])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'El campo nombre no puede tener más de 255 caracteres.');
});

it('keeps an unsupported language from leaking untranslated keys into the body', function () {
    // ?lang=fr falls through to the default rather than selecting a lang/fr that
    // does not exist, which would make Laravel echo "validation.required" back.
    $this->postJson('/api/businesses?lang=fr', [])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'El campo nombre es obligatorio.');
});
