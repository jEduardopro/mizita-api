<?php

declare(strict_types=1);

use App\Domains\Businesses\Exceptions\InvalidBusinessOwner;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('reports that the account registering the business could not be identified', function () {
    expect(InvalidBusinessOwner::missing()->getMessage())
        ->toBe('A business cannot be onboarded without an owner account.');
});

it('names no account in the refusal, because the whole point is that there was none', function () {
    expect(InvalidBusinessOwner::missing()->getMessage())->not->toContain('[');
});

it('answers with the error code the caller is shown a sentence for', function () {
    expect(InvalidBusinessOwner::missing()->errorCode())->toBe('invalid_business_owner');
});

it('classifies as a 422 rather than a 401, because the owner is a payload fact here', function () {
    expect(InvalidBusinessOwner::missing()->kind())->toBe(DomainFailureKind::Invalid);
});

it('carries the interface the renderer is registered against', function () {
    expect(InvalidBusinessOwner::missing())->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidBusinessOwner::missing()->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
