<?php

declare(strict_types=1);

use App\Domains\Customers\Exceptions\CustomerEmailAlreadyTaken;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('quotes the email already on record', function () {
    expect(CustomerEmailAlreadyTaken::for('ada@example.com')->getMessage())
        ->toBe('A customer with email [ada@example.com] already exists.');
});

it('answers with a stable error code', function () {
    expect(CustomerEmailAlreadyTaken::for('ada@example.com')->errorCode())->toBe('customer_email_taken');
});

it('classifies a taken email as a conflict rather than a refusal of the payload', function () {
    expect(CustomerEmailAlreadyTaken::for('ada@example.com')->kind())->toBe(DomainFailureKind::Conflict);
});

it('keeps the driver error it was raised from, so the log can name the constraint', function () {
    $violation = new RuntimeException('SQLSTATE[23505]: unique violation');

    expect(CustomerEmailAlreadyTaken::for('ada@example.com', $violation)->getPrevious())->toBe($violation);
});

it('stands alone when the clash was found by looking rather than by failing', function () {
    expect(CustomerEmailAlreadyTaken::for('ada@example.com')->getPrevious())->toBeNull();
});

it('carries the interface the renderer is registered against', function () {
    expect(CustomerEmailAlreadyTaken::for('ada@example.com'))->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][CustomerEmailAlreadyTaken::for('any')->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);

it('never shows the caller the address it quoted, because the message on the wire is the translation', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors']['customer_email_taken'])->not->toContain('ada@example.com');
})->with(['en', 'es']);
