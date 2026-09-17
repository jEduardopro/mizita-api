<?php

declare(strict_types=1);

use App\Domains\Customers\Exceptions\InvalidCustomerBirthDate;
use App\Domains\Customers\ValueObjects\BirthDate;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

describe('reading a birth date that may not be there', function () {
    it('reads no date as none rather than as a refusal', function (?string $raw) {
        expect(BirthDate::fromNullable($raw))->toBeNull();
    })->with(['null' => null, 'empty' => '', 'spaces' => '   ', 'tab' => "\t", 'newline' => "\n"]);

    it('hands back a plain instant rather than a wrapper, which is what the entity holds', function () {
        expect(BirthDate::fromNullable('1990-05-17'))->toBeInstanceOf(DateTimeImmutable::class);
    });

    it('reads the calendar date it was given', function () {
        expect(BirthDate::fromNullable('1990-05-17')?->format('Y-m-d'))->toBe('1990-05-17');
    });

    it('lands the date at midnight, so no hour of the day can ever creep in', function () {
        expect(BirthDate::fromNullable('1990-05-17')?->format('Y-m-d H:i:s'))->toBe('1990-05-17 00:00:00');
    });

    it('reads a leap day, which is a birthday people really have', function () {
        expect(BirthDate::fromNullable('2000-02-29')?->format('Y-m-d'))->toBe('2000-02-29');
    });
});

describe('refusing a date that is not one', function () {
    it('refuses anything that is not the format the column speaks', function (string $raw) {
        expect(fn () => BirthDate::fromNullable($raw))
            ->toThrow(InvalidCustomerBirthDate::class, 'is not a calendar date');
    })->with([
        'day first' => '17/05/1990',
        'dotted' => '17.05.1990',
        'slashes' => '1990/05/17',
        'no padding on the month' => '1990-5-17',
        'no padding on the day' => '1990-05-7',
        'a two digit year' => '90-05-17',
        'a word' => 'yesterday',
        'a timestamp' => '1990-05-17T00:00:00',
        'a date with a time' => '1990-05-17 10:00:00',
        'a unix timestamp' => '643161600',
        'only a year' => '1990',
        'a year and a month' => '1990-05',
        'padded' => ' 1990-05-17 ',
        'sql injection' => "1990-05-17'; drop table customers",
    ]);

    it('refuses a day the calendar does not have, rather than rolling it over', function (string $raw) {
        expect(fn () => BirthDate::fromNullable($raw))->toThrow(InvalidCustomerBirthDate::class);
    })->with([
        'the thirtieth of February' => '1990-02-30',
        'the thirty-second of January' => '1990-01-32',
        'a thirteenth month' => '1990-13-01',
        'a zeroth month' => '1990-00-01',
        'a zeroth day' => '1990-05-00',
        'the twenty-ninth of a February that was not a leap year' => '1900-02-29',
    ]);

    it('quotes the value it could not read', function () {
        expect(fn () => BirthDate::fromNullable('17/05/1990'))
            ->toThrow(InvalidCustomerBirthDate::class, 'The birth date [17/05/1990] is not a calendar date.');
    });
});

describe('what the parser deliberately leaves to the entity', function () {
    it('reads a date in the future without judging it, because being too late is not a parsing problem', function () {
        expect(BirthDate::fromNullable('2999-01-01')?->format('Y-m-d'))->toBe('2999-01-01');
    });

    it('reads a date before the earliest year a customer record allows', function () {
        expect(BirthDate::fromNullable('1799-01-01')?->format('Y-m-d'))->toBe('1799-01-01');
    });
});

it('refuses with a failure the responder can classify', function () {
    $refusal = null;

    try {
        BirthDate::fromNullable('not-a-date');
    } catch (InvalidCustomerBirthDate $caught) {
        $refusal = $caught;
    }

    expect($refusal)->toBeInstanceOf(DomainFailure::class)
        ->and($refusal?->errorCode())->toBe('invalid_customer_birth_date')
        ->and($refusal?->kind())->toBe(DomainFailureKind::Invalid);
});
