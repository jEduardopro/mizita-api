<?php

declare(strict_types=1);

use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\InvalidCustomerBirthDate;
use App\Domains\Customers\Exceptions\InvalidCustomerName;
use App\Domains\Customers\Exceptions\InvalidCustomerNotes;
use App\Domains\Customers\ValueObjects\CustomerEmail;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;

function customerNow(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-01-01 12:00:00');
}

function customerUnderTest(
    string $name = 'Ada Lovelace',
    ?CustomerEmail $email = null,
    ?DateTimeImmutable $birthDate = null,
    ?string $notes = null,
    ?DateTimeImmutable $now = null,
): Customer {
    return Customer::create(
        id: '01930000-0000-7000-8000-0000000000c1',
        businessId: FakeBusinessContext::BUSINESS_ID,
        name: $name,
        email: $email,
        birthDate: $birthDate,
        notes: $notes,
        now: $now ?? customerNow(),
    );
}

describe('creating a customer', function () {
    it('holds every fact it was given, pinned to the business that keeps it', function () {
        $customer = customerUnderTest(
            email: CustomerEmail::fromString('ada@example.com'),
            birthDate: new DateTimeImmutable('1990-05-17 00:00:00'),
            notes: 'Prefers the afternoon.',
        );

        expect($customer->id)->toBe('01930000-0000-7000-8000-0000000000c1')
            ->and($customer->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($customer->name())->toBe('Ada Lovelace')
            ->and($customer->email()?->value)->toBe('ada@example.com')
            ->and($customer->birthDate())->toEqual(new DateTimeImmutable('1990-05-17 00:00:00'))
            ->and($customer->notes())->toBe('Prefers the afternoon.')
            ->and($customer->createdAt)->toEqual(customerNow());
    });

    it('names its business by the uuid, never by a row number', function () {
        expect(customerUnderTest()->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and(customerUnderTest()->businessId)->not->toBe('1');
    });

    it('keeps a customer who gave nothing but a name', function () {
        $customer = customerUnderTest();

        expect($customer->email())->toBeNull()
            ->and($customer->birthDate())->toBeNull()
            ->and($customer->notes())->toBeNull();
    });

    it('is born at the instant it was handed, not at whatever the clock says later', function () {
        expect(customerUnderTest(now: new DateTimeImmutable('2024-03-31 01:30:00'))->createdAt)
            ->toEqual(new DateTimeImmutable('2024-03-31 01:30:00'));
    });
});

describe('the name', function () {
    it('trims the name it was handed', function () {
        expect(customerUnderTest(name: "  Ada Lovelace \t ")->name())->toBe('Ada Lovelace');
    });

    it('refuses a name that says nothing', function (string $name) {
        expect(fn () => customerUnderTest(name: $name))->toThrow(InvalidCustomerName::class);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'newline' => "\n",
        'every kind of blank at once' => " \t\n ",
    ]);

    it('accepts a name as long as the column holds', function () {
        expect(customerUnderTest(name: str_repeat('a', Customer::MAXIMUM_NAME_LENGTH))->name())
            ->toHaveLength(Customer::MAXIMUM_NAME_LENGTH);
    });

    it('refuses a name one character past what the column holds', function () {
        expect(fn () => customerUnderTest(name: str_repeat('a', Customer::MAXIMUM_NAME_LENGTH + 1)))
            ->toThrow(InvalidCustomerName::class, 'may not run past 120 characters');
    });

    it('measures the name after trimming it, so padding never costs a customer their name', function () {
        expect(customerUnderTest(name: '  '.str_repeat('a', Customer::MAXIMUM_NAME_LENGTH).'  ')->name())
            ->toHaveLength(Customer::MAXIMUM_NAME_LENGTH);
    });

    it('counts characters rather than bytes, so an accent is not worth two letters', function () {
        $name = str_repeat('á', Customer::MAXIMUM_NAME_LENGTH);

        expect(customerUnderTest(name: $name)->name())->toBe($name);
    });

    it('refuses an accented name one character too long, counting characters', function () {
        expect(fn () => customerUnderTest(name: str_repeat('á', Customer::MAXIMUM_NAME_LENGTH + 1)))
            ->toThrow(InvalidCustomerName::class);
    });

    it('keeps the accents and the casing a person writes their name with', function () {
        expect(customerUnderTest(name: 'José Ñuño McCarthy-Ruiz')->name())->toBe('José Ñuño McCarthy-Ruiz');
    });
});

describe('the birth date', function () {
    it('accepts a customer who gave no birth date', function () {
        expect(customerUnderTest(birthDate: null)->birthDate())->toBeNull();
    });

    it('refuses a birth date later than now', function () {
        expect(fn () => customerUnderTest(birthDate: new DateTimeImmutable('2026-01-01 12:00:01')))
            ->toThrow(InvalidCustomerBirthDate::class, 'cannot be later than today');
    });

    it('accepts a birth date falling exactly on now', function () {
        expect(customerUnderTest(birthDate: customerNow())->birthDate())->toEqual(customerNow());
    });

    it('refuses a birth date before the earliest year a record can mean', function () {
        expect(fn () => customerUnderTest(birthDate: new DateTimeImmutable('1899-12-31 00:00:00')))
            ->toThrow(InvalidCustomerBirthDate::class, 'cannot be earlier than the year 1900');
    });

    it('accepts the first day of the earliest year it allows', function () {
        expect(customerUnderTest(birthDate: new DateTimeImmutable('1900-01-01 00:00:00'))->birthDate())
            ->toEqual(new DateTimeImmutable('1900-01-01 00:00:00'));
    });

    it('accepts a leap day, which is a birthday people really have', function () {
        expect(customerUnderTest(birthDate: new DateTimeImmutable('2000-02-29 00:00:00'))->birthDate())
            ->toEqual(new DateTimeImmutable('2000-02-29 00:00:00'));
    });
});

describe('the notes', function () {
    it('accepts a customer nobody wrote anything about', function () {
        expect(customerUnderTest(notes: null)->notes())->toBeNull();
    });

    it('reads notes that say nothing as none at all, rather than refusing them', function (string $notes) {
        expect(customerUnderTest(notes: $notes)->notes())->toBeNull();
    })->with(['empty' => '', 'spaces' => '   ', 'tab' => "\t", 'newline' => "\n"]);

    it('trims the notes it keeps', function () {
        expect(customerUnderTest(notes: '  Allergic to ammonia.  ')->notes())->toBe('Allergic to ammonia.');
    });

    it('keeps notes as long as the column holds', function () {
        expect(customerUnderTest(notes: str_repeat('a', Customer::MAXIMUM_NOTES_LENGTH))->notes())
            ->toHaveLength(Customer::MAXIMUM_NOTES_LENGTH);
    });

    it('refuses notes one character past what the column holds', function () {
        expect(fn () => customerUnderTest(notes: str_repeat('a', Customer::MAXIMUM_NOTES_LENGTH + 1)))
            ->toThrow(InvalidCustomerNotes::class, 'may not run past 2000 characters');
    });

    it('keeps the line breaks a note was written with', function () {
        expect(customerUnderTest(notes: "First visit.\nAllergic to ammonia.")->notes())
            ->toBe("First visit.\nAllergic to ammonia.");
    });
});

describe('restoring a customer from persistence', function () {
    it('takes the row as it stands rather than judging it again', function () {
        $customer = Customer::restore(
            id: '01930000-0000-7000-8000-0000000000c1',
            businessId: FakeBusinessContext::BUSINESS_ID,
            name: '',
            email: CustomerEmail::restore('NOT-AN-EMAIL'),
            birthDate: new DateTimeImmutable('1800-01-01 00:00:00'),
            notes: str_repeat('a', Customer::MAXIMUM_NOTES_LENGTH + 1),
            createdAt: customerNow(),
        );

        expect($customer->name())->toBe('')
            ->and($customer->email()?->value)->toBe('NOT-AN-EMAIL')
            ->and($customer->birthDate())->toEqual(new DateTimeImmutable('1800-01-01 00:00:00'))
            ->and($customer->notes())->toHaveLength(Customer::MAXIMUM_NOTES_LENGTH + 1);
    });

    it('leaves the name exactly as the row spells it, padding included', function () {
        $customer = Customer::restore(
            id: '01930000-0000-7000-8000-0000000000c1',
            businessId: FakeBusinessContext::BUSINESS_ID,
            name: '  Ada  ',
            email: null,
            birthDate: null,
            notes: null,
            createdAt: customerNow(),
        );

        expect($customer->name())->toBe('  Ada  ');
    });

    it('is born at the instant the row records, not at the instant it was read', function () {
        $customer = Customer::restore(
            id: '01930000-0000-7000-8000-0000000000c1',
            businessId: FakeBusinessContext::BUSINESS_ID,
            name: 'Ada',
            email: null,
            birthDate: null,
            notes: null,
            createdAt: new DateTimeImmutable('2023-07-04 08:00:00'),
        );

        expect($customer->createdAt)->toEqual(new DateTimeImmutable('2023-07-04 08:00:00'));
    });
});

describe('renaming a customer', function () {
    it('answers to the new name', function () {
        $customer = customerUnderTest();

        $customer->rename('Ada Byron');

        expect($customer->name())->toBe('Ada Byron');
    });

    it('trims the new name as readily as the first one', function () {
        $customer = customerUnderTest();

        $customer->rename('  Ada Byron  ');

        expect($customer->name())->toBe('Ada Byron');
    });

    it('refuses a new name that says nothing', function (string $name) {
        expect(fn () => customerUnderTest()->rename($name))->toThrow(InvalidCustomerName::class);
    })->with(['empty' => '', 'spaces' => '   ', 'too long' => str_repeat('a', 121)]);

    it('keeps the name it had when the new one is refused', function () {
        $customer = customerUnderTest(name: 'Ada Lovelace');

        try {
            $customer->rename('   ');
        } catch (InvalidCustomerName) {
        }

        expect($customer->name())->toBe('Ada Lovelace');
    });
});

describe('changing the email', function () {
    it('answers to the new email', function () {
        $customer = customerUnderTest(email: CustomerEmail::fromString('ada@example.com'));

        $customer->changeEmail(CustomerEmail::fromString('ada.byron@example.com'));

        expect($customer->email()?->value)->toBe('ada.byron@example.com');
    });

    it('lets a customer be left with no email at all', function () {
        $customer = customerUnderTest(email: CustomerEmail::fromString('ada@example.com'));

        $customer->changeEmail(null);

        expect($customer->email())->toBeNull();
    });

    it('takes an email a customer never had', function () {
        $customer = customerUnderTest();

        $customer->changeEmail(CustomerEmail::fromString('ada@example.com'));

        expect($customer->email()?->value)->toBe('ada@example.com');
    });
});

describe('changing the birth date', function () {
    it('answers to the new birth date', function () {
        $customer = customerUnderTest();

        $customer->changeBirthDate(new DateTimeImmutable('1990-05-17 00:00:00'), customerNow());

        expect($customer->birthDate())->toEqual(new DateTimeImmutable('1990-05-17 00:00:00'));
    });

    it('lets a customer be left with no birth date at all', function () {
        $customer = customerUnderTest(birthDate: new DateTimeImmutable('1990-05-17 00:00:00'));

        $customer->changeBirthDate(null, customerNow());

        expect($customer->birthDate())->toBeNull();
    });

    it('holds a change to the same rules creation is held to', function (DateTimeImmutable $birthDate) {
        expect(fn () => customerUnderTest()->changeBirthDate($birthDate, customerNow()))
            ->toThrow(InvalidCustomerBirthDate::class);
    })->with([
        'later than now' => fn () => new DateTimeImmutable('2026-01-01 12:00:01'),
        'before the earliest year' => fn () => new DateTimeImmutable('1899-12-31 00:00:00'),
    ]);

    it('keeps the birth date it had when the new one is refused', function () {
        $customer = customerUnderTest(birthDate: new DateTimeImmutable('1990-05-17 00:00:00'));

        try {
            $customer->changeBirthDate(new DateTimeImmutable('2030-01-01 00:00:00'), customerNow());
        } catch (InvalidCustomerBirthDate) {
        }

        expect($customer->birthDate())->toEqual(new DateTimeImmutable('1990-05-17 00:00:00'));
    });

    it('judges the date against the now it is handed, not against a clock of its own', function () {
        $customer = customerUnderTest();

        $customer->changeBirthDate(
            new DateTimeImmutable('2025-06-01 00:00:00'),
            new DateTimeImmutable('2025-06-01 00:00:00'),
        );

        expect($customer->birthDate())->toEqual(new DateTimeImmutable('2025-06-01 00:00:00'))
            ->and(fn () => customerUnderTest()->changeBirthDate(
                new DateTimeImmutable('2025-06-01 00:00:00'),
                new DateTimeImmutable('2025-05-31 23:59:59'),
            ))->toThrow(InvalidCustomerBirthDate::class);
    });
});

describe('changing the notes', function () {
    it('answers to the new notes', function () {
        $customer = customerUnderTest();

        $customer->changeNotes('  Allergic to ammonia.  ');

        expect($customer->notes())->toBe('Allergic to ammonia.');
    });

    it('lets a customer be left with no notes at all', function (?string $notes) {
        $customer = customerUnderTest(notes: 'Allergic to ammonia.');

        $customer->changeNotes($notes);

        expect($customer->notes())->toBeNull();
    })->with(['null' => null, 'empty' => '', 'spaces' => '   ']);

    it('refuses notes past what the column holds', function () {
        expect(fn () => customerUnderTest()->changeNotes(str_repeat('a', Customer::MAXIMUM_NOTES_LENGTH + 1)))
            ->toThrow(InvalidCustomerNotes::class);
    });

    it('keeps the notes it had when the new ones are refused', function () {
        $customer = customerUnderTest(notes: 'Allergic to ammonia.');

        try {
            $customer->changeNotes(str_repeat('a', Customer::MAXIMUM_NOTES_LENGTH + 1));
        } catch (InvalidCustomerNotes) {
        }

        expect($customer->notes())->toBe('Allergic to ammonia.');
    });
});

it('refuses with a failure the responder can classify', function (callable $attempt, string $code) {
    $refusal = null;

    try {
        $attempt();
    } catch (DomainFailure $caught) {
        $refusal = $caught;
    }

    expect($refusal)->toBeInstanceOf(DomainFailure::class)
        ->and($refusal?->errorCode())->toBe($code)
        ->and($refusal?->kind())->toBe(DomainFailureKind::Invalid);
})->with([
    'a blank name' => [fn () => customerUnderTest(name: ''), 'invalid_customer_name'],
    'a birth date in the future' => [
        fn () => customerUnderTest(birthDate: new DateTimeImmutable('2030-01-01 00:00:00')),
        'invalid_customer_birth_date',
    ],
    'notes past the limit' => [
        fn () => customerUnderTest(notes: str_repeat('a', 2001)),
        'invalid_customer_notes',
    ],
]);
