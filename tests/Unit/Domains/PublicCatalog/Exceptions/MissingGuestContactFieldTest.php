<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Exceptions\MissingGuestContactField;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

dataset('missing guest contact fields', [
    'phone' => [fn () => MissingGuestContactField::phone(), 'missing_guest_phone'],
    'email' => [fn () => MissingGuestContactField::email(), 'missing_guest_email'],
    'address' => [fn () => MissingGuestContactField::address(), 'missing_guest_address'],
]);

it('names the field the visitor left out in its error code', function (MissingGuestContactField $refusal, string $code) {
    expect($refusal->errorCode())->toBe($code);
})->with('missing guest contact fields');

it('is an invalid payload refusal, whichever field is missing', function (MissingGuestContactField $refusal) {
    expect($refusal)->toBeInstanceOf(DomainFailure::class)
        ->and($refusal->kind())->toBe(DomainFailureKind::Invalid);
})->with('missing guest contact fields');

it('has a sentence to show the visitor in every locale', function (MissingGuestContactField $refusal, string $code, string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][$code] ?? '')->toBeString()->not->toBe('');
})->with('missing guest contact fields')->with(['en', 'es']);
