<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withHeader('Accept-Language', '');

    Sanctum::actingAs(User::factory()->create());
});

it('answers a validation failure in the default locale', function () {
    $this->postJson('/api/businesses', [])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'El campo nombre es obligatorio.')
        ->assertJsonPath('errors.timezone.0', 'El campo zona horaria es obligatorio.');
});

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
    $this->postJson('/api/businesses', [], ['Accept-Language' => 'en-US,en;q=0.9'])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'El campo nombre es obligatorio.');
});

it('answers a validation failure in english when the locale cookie asks for it', function () {
    $this->withCredentials()
        ->withUnencryptedCookie('locale', 'en')
        ->postJson('/api/businesses', [])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'The name field is required.');
});

it('translates the attribute name inside a rule that is not required', function () {
    $this->postJson('/api/businesses', [
        'name' => str_repeat('a', 121),
        'timezone' => 'Europe/Madrid',
        'industry_id' => '01930000-0000-7000-8000-0000000000f1',
    ])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'El campo nombre no puede tener más de 120 caracteres.');
});

it('keeps an unsupported language from leaking untranslated keys into the body', function () {
    $this->postJson('/api/businesses?lang=fr', [])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'El campo nombre es obligatorio.');
});
