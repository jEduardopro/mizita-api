<?php

declare(strict_types=1);

use App\Http\Responses\WarningEnvelope;
use App\Shared\Application\Warning;
use Tests\TestCase;

uses(TestCase::class);

function registerEnvelopeWarningLine(string $locale, string $code, string $message): void
{
    $translator = app('translator');
    $translator->get('messages.warnings', [], $locale);
    $translator->addLines(['messages.warnings.'.$code => $message], $locale);
}

beforeEach(function () {
    app()->setLocale('en');

    registerEnvelopeWarningLine('en', 'phone_not_saved', 'We could not save the phone number.');
    registerEnvelopeWarningLine('en', 'welcome_email_not_sent', 'We could not send the welcome email.');
});

it('returns nothing at all for an empty list, so every consumer omits the key', function () {
    expect(WarningEnvelope::for([]))->toBe([]);
});

it('describes a single warning with its code and its translation', function () {
    expect(WarningEnvelope::for([new Warning('phone_not_saved')]))->toBe([
        'warnings' => [
            ['code' => 'phone_not_saved', 'message' => 'We could not save the phone number.'],
        ],
    ]);
});

it('keeps several warnings in the order they were reported', function () {
    $envelope = WarningEnvelope::for([
        new Warning('welcome_email_not_sent'),
        new Warning('phone_not_saved'),
    ]);

    expect(array_column($envelope['warnings'], 'code'))
        ->toBe(['welcome_email_not_sent', 'phone_not_saved']);
});

it('describes a warning with exactly a code and a message', function () {
    $envelope = WarningEnvelope::for([new Warning('phone_not_saved')]);

    expect(array_keys($envelope))->toBe(['warnings'])
        ->and(array_keys($envelope['warnings'][0]))->toBe(['code', 'message']);
});

it('indexes the warnings as a list, never as an object', function () {
    $envelope = WarningEnvelope::for([
        new Warning('welcome_email_not_sent'),
        new Warning('phone_not_saved'),
    ]);

    expect(array_is_list($envelope['warnings']))->toBeTrue();
});

it('translates the message in the locale the request resolved', function () {
    app()->setLocale('es');
    registerEnvelopeWarningLine('es', 'phone_not_saved', 'No hemos podido guardar el teléfono.');

    expect(WarningEnvelope::for([new Warning('phone_not_saved')])['warnings'][0]['message'])
        ->toBe('No hemos podido guardar el teléfono.');
});
