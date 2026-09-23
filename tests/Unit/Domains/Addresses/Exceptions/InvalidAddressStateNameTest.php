<?php

declare(strict_types=1);

use App\Domains\Addresses\Exceptions\InvalidAddressStateName;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('says the state name offered is longer than one may be', function () {
    expect(InvalidAddressStateName::tooLong()->getMessage())
        ->toBe('The state name offered is longer than an address state name may be.');
});

it('never quotes the state name back, because the message is shown to the caller who typed it', function () {
    expect(InvalidAddressStateName::tooLong()->getMessage())->not->toContain('[');
});

it('answers with its own error code', function () {
    expect(InvalidAddressStateName::tooLong()->errorCode())->toBe('invalid_address_state_name');
});

it('classifies a state name that is too long as a 422 rather than a conflict or a 500', function () {
    expect(InvalidAddressStateName::tooLong()->kind())->toBe(DomainFailureKind::Invalid);
});

it('carries the interface the renderer is registered against', function () {
    expect(InvalidAddressStateName::tooLong())->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidAddressStateName::tooLong()->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
