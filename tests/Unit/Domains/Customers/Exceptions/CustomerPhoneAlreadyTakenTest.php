<?php

declare(strict_types=1);

use App\Domains\Customers\Exceptions\CustomerPhoneAlreadyTaken;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\PhoneNumbers;

it('quotes the number already on record', function () {
    expect(CustomerPhoneAlreadyTaken::for(PhoneNumbers::MX_E164)->getMessage())
        ->toBe('A customer with phone number ['.PhoneNumbers::MX_E164.'] already exists.');
});

it('answers with a stable error code', function () {
    expect(CustomerPhoneAlreadyTaken::for(PhoneNumbers::MX_E164)->errorCode())->toBe('customer_phone_taken');
});

it('classifies a taken number as a conflict rather than a refusal of the payload', function () {
    expect(CustomerPhoneAlreadyTaken::for(PhoneNumbers::MX_E164)->kind())->toBe(DomainFailureKind::Conflict);
});

it('carries the interface the renderer is registered against', function () {
    expect(CustomerPhoneAlreadyTaken::for(PhoneNumbers::MX_E164))->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][CustomerPhoneAlreadyTaken::for('any')->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);

it('never shows the caller the number it quoted, because the message on the wire is the translation', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors']['customer_phone_taken'])->not->toContain(PhoneNumbers::MX_E164);
})->with(['en', 'es']);
