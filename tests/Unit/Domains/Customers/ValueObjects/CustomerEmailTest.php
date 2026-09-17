<?php

declare(strict_types=1);

use App\Domains\Customers\Exceptions\InvalidCustomerEmail;
use App\Domains\Customers\ValueObjects\CustomerEmail;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

function emailOfLength(int $length): string
{
    $domain = str_repeat('a', 63).'.'.str_repeat('b', 60).'.'.str_repeat('c', 60).'.com';

    return str_repeat('a', $length - mb_strlen($domain) - 1).'@'.$domain;
}

describe('reading an email', function () {
    it('keeps a well formed address', function () {
        expect(CustomerEmail::fromString('ada@example.com')->value)->toBe('ada@example.com');
    });

    it('trims the address it was handed', function (string $raw) {
        expect(CustomerEmail::fromString($raw)->value)->toBe('ada@example.com');
    })->with([
        'padded' => '  ada@example.com  ',
        'tab' => "\tada@example.com",
        'newline' => "ada@example.com\n",
    ]);

    it('folds the domain, which is case insensitive, and leaves the mailbox alone', function () {
        expect(CustomerEmail::fromString('Ada.Lovelace@EXAMPLE.COM')->value)
            ->toBe('Ada.Lovelace@example.com');
    });

    it('folds only the last part, so an address is never cut at the wrong sign', function () {
        expect(CustomerEmail::fromString('"a@b"@EXAMPLE.COM')->value)->toBe('"a@b"@example.com');
    });

    it('accepts the shapes a real mailbox comes in', function (string $raw) {
        expect(CustomerEmail::fromString($raw)->value)->toBe($raw);
    })->with([
        'a plus tag' => 'ada+booking@example.com',
        'a dotted mailbox' => 'ada.lovelace@example.com',
        'a subdomain' => 'ada@mail.example.com',
        'a hyphenated domain' => 'ada@my-salon.example',
        'digits' => 'ada1815@example.com',
        'an underscore' => 'ada_lovelace@example.com',
    ]);

    it('accepts an address as long as the column holds', function () {
        expect(CustomerEmail::fromString(emailOfLength(CustomerEmail::MAXIMUM_LENGTH))->value)
            ->toHaveLength(CustomerEmail::MAXIMUM_LENGTH);
    });
});

describe('refusing an email', function () {
    it('refuses an address that says nothing', function (string $raw) {
        expect(fn () => CustomerEmail::fromString($raw))
            ->toThrow(InvalidCustomerEmail::class, 'cannot be empty');
    })->with(['empty' => '', 'spaces' => '   ', 'tab' => "\t", 'newline' => "\n"]);

    it('refuses an address one character past what the column holds', function () {
        expect(fn () => CustomerEmail::fromString(emailOfLength(CustomerEmail::MAXIMUM_LENGTH + 1)))
            ->toThrow(InvalidCustomerEmail::class, 'longer than a customer email may be');
    });

    it('measures before it parses, so an overlong address is never reported as malformed', function () {
        expect(fn () => CustomerEmail::fromString(str_repeat('a', 300)))
            ->toThrow(InvalidCustomerEmail::class, 'longer than a customer email may be');
    });

    it('refuses anything that is not an address', function (string $raw) {
        expect(fn () => CustomerEmail::fromString($raw))
            ->toThrow(InvalidCustomerEmail::class, 'not a valid email address');
    })->with([
        'no sign' => 'ada.example.com',
        'no mailbox' => '@example.com',
        'no domain' => 'ada@',
        'two signs' => 'ada@@example.com',
        'a space inside' => 'ada lovelace@example.com',
        'a bare word' => 'ada',
        'a dot for a domain' => 'ada@.',
        'a comma for a dot' => 'ada@example,com',
        'a name and an address' => 'Ada Lovelace <ada@example.com>',
    ]);

    it('measures the address after trimming it', function () {
        $padded = '  '.emailOfLength(CustomerEmail::MAXIMUM_LENGTH).'  ';

        expect(CustomerEmail::fromString($padded)->value)->toHaveLength(CustomerEmail::MAXIMUM_LENGTH);
    });
});

describe('reading an email that may not be there', function () {
    it('reads no email as none rather than as a refusal', function (?string $raw) {
        expect(CustomerEmail::fromNullable($raw))->toBeNull();
    })->with(['null' => null, 'empty' => '', 'spaces' => '   ', 'tab' => "\t"]);

    it('holds an address to every rule the strict reading applies', function () {
        expect(fn () => CustomerEmail::fromNullable('not-an-email'))->toThrow(InvalidCustomerEmail::class);
    });

    it('returns the same value object the strict reading builds', function () {
        expect(CustomerEmail::fromNullable('  Ada@EXAMPLE.COM  ')?->value)->toBe('Ada@example.com');
    });
});

describe('restoring an email from persistence', function () {
    it('takes the row as it stands rather than judging it again', function () {
        expect(CustomerEmail::restore('NOT-AN-EMAIL')->value)->toBe('NOT-AN-EMAIL');
    });

    it('folds nothing, so a row reads back exactly as it was written', function () {
        expect(CustomerEmail::restore('Ada@EXAMPLE.COM')->value)->toBe('Ada@EXAMPLE.COM');
    });
});

describe('comparing two emails', function () {
    it('finds two addresses spelled the same to be the same', function () {
        expect(CustomerEmail::fromString('ada@example.com')->equals(CustomerEmail::fromString('ada@example.com')))
            ->toBeTrue();
    });

    it('finds two different addresses to be different', function () {
        expect(CustomerEmail::fromString('ada@example.com')->equals(CustomerEmail::fromString('grace@example.com')))
            ->toBeFalse();
    });

    it('finds two addresses differing only in the domain case to be the same, because the domain is folded', function () {
        expect(CustomerEmail::fromString('ada@EXAMPLE.COM')->equals(CustomerEmail::fromString('ada@example.com')))
            ->toBeTrue();
    });

    it('tells apart two addresses differing in the mailbox case, which the fold deliberately leaves alone', function () {
        expect(CustomerEmail::fromString('Ada@example.com')->equals(CustomerEmail::fromString('ada@example.com')))
            ->toBeFalse();
    });
});

it('refuses with a failure the responder can classify', function () {
    $refusal = null;

    try {
        CustomerEmail::fromString('not-an-email');
    } catch (InvalidCustomerEmail $caught) {
        $refusal = $caught;
    }

    expect($refusal)->toBeInstanceOf(DomainFailure::class)
        ->and($refusal?->errorCode())->toBe('invalid_customer_email')
        ->and($refusal?->kind())->toBe(DomainFailureKind::Invalid);
});
