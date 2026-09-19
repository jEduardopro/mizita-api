<?php

declare(strict_types=1);

use App\Domains\BookingPolicies\Exceptions\InvalidPolicyMessage;
use App\Domains\BookingPolicies\ValueObjects\PolicyMessage;
use App\Shared\ValueObjects\DomainFailureKind;

it('keeps the message the business wrote, trimmed', function () {
    expect(PolicyMessage::fromString('  Cancela con antelación.  ')->toString())
        ->toBe('Cancela con antelación.');
});

it('keeps unicode, accents and line breaks untouched', function () {
    $message = "Barbería Ñandú\nCancela con antelación.";

    expect(PolicyMessage::fromString($message)->toString())->toBe($message);
});

it('accepts a message of exactly the maximum length', function () {
    $message = str_repeat('a', PolicyMessage::MAXIMUM_LENGTH);

    expect(PolicyMessage::fromString($message)->toString())->toBe($message);
});

it('refuses a message one character past the maximum length', function () {
    expect(fn () => PolicyMessage::fromString(str_repeat('a', PolicyMessage::MAXIMUM_LENGTH + 1)))
        ->toThrow(InvalidPolicyMessage::class);
});

it('measures the length in characters, not in bytes', function () {
    expect(PolicyMessage::fromString(str_repeat('ñ', PolicyMessage::MAXIMUM_LENGTH))->toString())
        ->toBe(str_repeat('ñ', PolicyMessage::MAXIMUM_LENGTH));
});

it('refuses as a domain failure the responder can classify', function () {
    try {
        PolicyMessage::fromString(str_repeat('a', PolicyMessage::MAXIMUM_LENGTH + 1));
        $thrown = null;
    } catch (InvalidPolicyMessage $refusal) {
        $thrown = $refusal;
    }

    expect($thrown?->errorCode())->toBe('invalid_policy_message')
        ->and($thrown?->kind())->toBe(DomainFailureKind::Invalid);
});

it('collapses a message that says nothing into no message at all', function (?string $blank) {
    expect(PolicyMessage::fromString($blank)->toString())->toBeNull();
})->with([
    'null' => null,
    'an empty string' => '',
    'spaces' => '   ',
    'a tab' => "\t",
    'a line break' => "\n",
]);

it('holds nothing at all for a business that publishes no policy message', function () {
    expect(PolicyMessage::none()->toString())->toBeNull();
});

it('restores whatever the column holds, skipping the rules that guard a new value', function () {
    expect(PolicyMessage::restore('   ')->toString())->toBe('   ')
        ->and(PolicyMessage::restore(null)->toString())->toBeNull();
});

it('holds two equal messages to be the same', function () {
    expect(PolicyMessage::fromString('Cancela.')->equals(PolicyMessage::fromString('Cancela.')))->toBeTrue()
        ->and(PolicyMessage::none()->equals(PolicyMessage::fromString('   ')))->toBeTrue()
        ->and(PolicyMessage::none()->equals(PolicyMessage::fromString('Cancela.')))->toBeFalse();
});
