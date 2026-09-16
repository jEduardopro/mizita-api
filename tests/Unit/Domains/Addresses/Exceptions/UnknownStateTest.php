<?php

declare(strict_types=1);

use App\Domains\Addresses\Exceptions\UnknownState;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Addresses\AddressFixtures;

it('quotes the state it could not find', function () {
    expect(UnknownState::withId(AddressFixtures::STATE_ID)->getMessage())
        ->toBe('State ['.AddressFixtures::STATE_ID.'] is not on record.');
});

it('answers with a stable error code', function () {
    expect(UnknownState::withId(AddressFixtures::STATE_ID)->errorCode())->toBe('unknown_state');
});

it('classifies a state the caller picked as a refusal of the payload, not a missing page', function () {
    expect(UnknownState::withId(AddressFixtures::STATE_ID)->kind())->toBe(DomainFailureKind::Invalid);
});

it('carries the interface the renderer is registered against', function () {
    expect(UnknownState::withId(AddressFixtures::STATE_ID))->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][UnknownState::withId('any')->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
