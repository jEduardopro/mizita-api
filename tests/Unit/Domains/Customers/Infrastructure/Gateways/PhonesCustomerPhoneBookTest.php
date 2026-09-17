<?php

declare(strict_types=1);

use App\Domains\Customers\Contracts\CustomerPhoneBook;
use App\Domains\Customers\Infrastructure\Gateways\PhonesCustomerPhoneBook;
use App\Domains\Phones\Application\UseCases\AttachPhone;
use App\Domains\Phones\Contracts\PhoneRepository;
use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\ValueObjects\PhoneNumberFragment;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Exceptions\UseCaseFailed;
use App\Shared\ValueObjects\DomainFailureKind;
use App\Shared\ValueObjects\PhoneNumber;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;
use Tests\Support\PhoneNumbers;

const CUSTOMER_PHONE_BOOK_CUSTOMER_ID = '01930000-0000-7000-8000-0000000000c1';

const CUSTOMER_PHONE_BOOK_SECOND_CUSTOMER_ID = '01930000-0000-7000-8000-0000000000c2';

const CUSTOMER_PHONE_BOOK_OTHER_BUSINESS_CUSTOMER_ID = '01930000-0000-7000-8000-0000000000c9';

const CUSTOMER_PHONE_BOOK_PHONE_ID = '01930000-0000-7000-8000-0000000000e1';

function customerPhoneRecord(
    string $customerId = CUSTOMER_PHONE_BOOK_CUSTOMER_ID,
    ?PhoneNumber $number = null,
): Phone {
    return Phone::restore(
        id: CUSTOMER_PHONE_BOOK_PHONE_ID,
        ownerType: PhoneOwnerType::Customer,
        ownerId: $customerId,
        number: $number ?? PhoneNumbers::mexican(),
        createdAt: new DateTimeImmutable('2026-01-01T12:00:00+00:00'),
    );
}

beforeEach(function () {
    $this->phones = Mockery::mock(PhoneRepository::class);

    $this->phoneBook = new PhonesCustomerPhoneBook(
        new AttachPhone(
            $this->phones,
            new FixedIdGenerator(CUSTOMER_PHONE_BOOK_PHONE_ID),
            new FakeClock(new DateTimeImmutable('2026-01-01T12:00:00+00:00')),
        ),
        $this->phones,
    );

    $this->read = fn (): ?PhoneNumber => $this->phoneBook->forCustomer(CUSTOMER_PHONE_BOOK_CUSTOMER_ID);

    $this->replace = fn (?PhoneNumber $number): mixed => $this->phoneBook->replaceForCustomer(
        CUSTOMER_PHONE_BOOK_CUSTOMER_ID,
        $number,
    );

    $this->remove = fn (): mixed => $this->phoneBook->removeForCustomer(CUSTOMER_PHONE_BOOK_CUSTOMER_ID);
});

describe('reading the number a customer filed', function () {
    it('answers with nothing when the customer filed no number', function () {
        $this->phones->shouldReceive('findForOwner')->once()
            ->with(PhoneOwnerType::Customer, CUSTOMER_PHONE_BOOK_CUSTOMER_ID)
            ->andReturnNull();

        expect(($this->read)())->toBeNull();
    });

    it('hands back the number itself, never the row that holds it', function () {
        $this->phones->shouldReceive('findForOwner')->once()->andReturn(customerPhoneRecord());

        $number = ($this->read)();

        expect($number)->toBeInstanceOf(PhoneNumber::class)
            ->and($number->e164())->toBe(PhoneNumbers::MX_E164);
    });

    it('asks for the number under the customer uuid, never an internal key', function () {
        $ownerId = null;

        $this->phones->shouldReceive('findForOwner')->once()
            ->with(PhoneOwnerType::Customer, Mockery::capture($ownerId))
            ->andReturnNull();

        ($this->read)();

        expect($ownerId)->toBe(CUSTOMER_PHONE_BOOK_CUSTOMER_ID)
            ->toBeString()
            ->and(is_numeric($ownerId))->toBeFalse();
    });
});

describe('reading a whole page of numbers at once', function () {
    it('asks the repository once for the entire page', function () {
        $this->phones->shouldReceive('findForOwners')->once()
            ->with(PhoneOwnerType::Customer, [
                CUSTOMER_PHONE_BOOK_CUSTOMER_ID,
                CUSTOMER_PHONE_BOOK_SECOND_CUSTOMER_ID,
            ])
            ->andReturn([]);

        $this->phoneBook->forCustomers([
            CUSTOMER_PHONE_BOOK_CUSTOMER_ID,
            CUSTOMER_PHONE_BOOK_SECOND_CUSTOMER_ID,
        ]);
    });

    it('keys the numbers by the customer uuid they belong to', function () {
        $this->phones->shouldReceive('findForOwners')->once()->andReturn([
            CUSTOMER_PHONE_BOOK_CUSTOMER_ID => customerPhoneRecord(),
            CUSTOMER_PHONE_BOOK_SECOND_CUSTOMER_ID => customerPhoneRecord(
                CUSTOMER_PHONE_BOOK_SECOND_CUSTOMER_ID,
                PhoneNumbers::american(),
            ),
        ]);

        $numbers = $this->phoneBook->forCustomers([
            CUSTOMER_PHONE_BOOK_CUSTOMER_ID,
            CUSTOMER_PHONE_BOOK_SECOND_CUSTOMER_ID,
        ]);

        expect(array_keys($numbers))->toBe([
            CUSTOMER_PHONE_BOOK_CUSTOMER_ID,
            CUSTOMER_PHONE_BOOK_SECOND_CUSTOMER_ID,
        ])
            ->and($numbers[CUSTOMER_PHONE_BOOK_CUSTOMER_ID])->toBeInstanceOf(PhoneNumber::class)
            ->and($numbers[CUSTOMER_PHONE_BOOK_CUSTOMER_ID]->e164())->toBe(PhoneNumbers::MX_E164)
            ->and($numbers[CUSTOMER_PHONE_BOOK_SECOND_CUSTOMER_ID]->e164())->toBe(PhoneNumbers::US_E164);
    });

    it('leaves a customer with no number out of the map instead of listing it as null', function () {
        $this->phones->shouldReceive('findForOwners')->once()->andReturn([
            CUSTOMER_PHONE_BOOK_CUSTOMER_ID => customerPhoneRecord(),
        ]);

        $numbers = $this->phoneBook->forCustomers([
            CUSTOMER_PHONE_BOOK_CUSTOMER_ID,
            CUSTOMER_PHONE_BOOK_SECOND_CUSTOMER_ID,
        ]);

        expect($numbers)->toHaveCount(1)
            ->and(array_key_exists(CUSTOMER_PHONE_BOOK_SECOND_CUSTOMER_ID, $numbers))->toBeFalse();
    });

    it('asks nothing at all when the page carries no customers', function () {
        $this->phones->shouldNotReceive('findForOwners');
        $this->phones->shouldNotReceive('findForOwner');

        expect($this->phoneBook->forCustomers([]))->toBe([]);
    });

    it('asks for one number per customer and never one query each', function () {
        $this->phones->shouldReceive('findForOwners')->once()->andReturn([]);
        $this->phones->shouldNotReceive('findForOwner');

        $this->phoneBook->forCustomers([
            CUSTOMER_PHONE_BOOK_CUSTOMER_ID,
            CUSTOMER_PHONE_BOOK_SECOND_CUSTOMER_ID,
        ]);
    });
});

describe('replacing the number a customer submitted', function () {
    it('files the submitted number against the customer, as a customer number', function () {
        $this->phones->shouldReceive('findForOwner')->once()
            ->with(PhoneOwnerType::Customer, CUSTOMER_PHONE_BOOK_CUSTOMER_ID)
            ->andReturnNull();

        $saved = null;
        $this->phones->shouldReceive('save')->once()->with(Mockery::capture($saved));
        $this->phones->shouldNotReceive('deleteForOwner');

        ($this->replace)(PhoneNumbers::mexican());

        expect($saved)->toBeInstanceOf(Phone::class)
            ->and($saved->id)->toBe(CUSTOMER_PHONE_BOOK_PHONE_ID)
            ->and($saved->ownerType)->toBe(PhoneOwnerType::Customer)
            ->and($saved->ownerId)->toBe(CUSTOMER_PHONE_BOOK_CUSTOMER_ID)
            ->and($saved->number()->e164())->toBe(PhoneNumbers::MX_E164);
    });

    it('updates the number the customer already had rather than filing a second one', function () {
        $existing = customerPhoneRecord();

        $this->phones->shouldReceive('findForOwner')->once()->andReturn($existing);
        $this->phones->shouldReceive('save')->once()->with($existing);

        ($this->replace)(PhoneNumbers::american());

        expect($existing->id)->toBe(CUSTOMER_PHONE_BOOK_PHONE_ID)
            ->and($existing->number()->e164())->toBe(PhoneNumbers::US_E164);
    });

    it('deletes the number the customer had when it submits none, and files nothing', function () {
        $this->phones->shouldReceive('deleteForOwner')->once()
            ->with(PhoneOwnerType::Customer, CUSTOMER_PHONE_BOOK_CUSTOMER_ID);
        $this->phones->shouldNotReceive('save');
        $this->phones->shouldNotReceive('findForOwner');

        expect(($this->replace)(null))->toBeNull();
    });
});

describe('deleting the number a customer filed', function () {
    it('deletes under the customer uuid, as a customer number', function () {
        $this->phones->shouldReceive('deleteForOwner')->once()
            ->with(PhoneOwnerType::Customer, CUSTOMER_PHONE_BOOK_CUSTOMER_ID);
        $this->phones->shouldNotReceive('save');

        expect(($this->remove)())->toBeNull();
    });

    it('lets a repository error out untouched', function () {
        $bug = new RuntimeException('the phones table is gone');

        $this->phones->shouldReceive('deleteForOwner')->once()->andThrow($bug);

        expect(fn () => ($this->remove)())->toThrow($bug);
    });
});

describe('looking a number up across every business', function () {
    it('hands back every customer holding the number, whatever business they belong to', function () {
        $this->phones->shouldReceive('ownerIdsWithNumber')->once()
            ->with(PhoneOwnerType::Customer, Mockery::type(PhoneNumber::class))
            ->andReturn([
                CUSTOMER_PHONE_BOOK_CUSTOMER_ID,
                CUSTOMER_PHONE_BOOK_OTHER_BUSINESS_CUSTOMER_ID,
            ]);

        expect($this->phoneBook->customerIdsWithNumber(PhoneNumbers::mexican()))->toBe([
            CUSTOMER_PHONE_BOOK_CUSTOMER_ID,
            CUSTOMER_PHONE_BOOK_OTHER_BUSINESS_CUSTOMER_ID,
        ]);
    });

    it('hands back every customer whose number contains the fragment, whatever business they belong to', function () {
        $this->phones->shouldReceive('ownerIdsMatchingNumber')->once()->andReturn([
            CUSTOMER_PHONE_BOOK_CUSTOMER_ID,
            CUSTOMER_PHONE_BOOK_OTHER_BUSINESS_CUSTOMER_ID,
        ]);

        expect($this->phoneBook->customerIdsMatchingNumber('5512'))->toBe([
            CUSTOMER_PHONE_BOOK_CUSTOMER_ID,
            CUSTOMER_PHONE_BOOK_OTHER_BUSINESS_CUSTOMER_ID,
        ]);
    });

    it('takes no business to narrow the lookup by, because the customer repository filters afterwards', function (string $method) {
        $parameters = (new ReflectionMethod(CustomerPhoneBook::class, $method))->getParameters();

        expect($parameters)->toHaveCount(1)
            ->and($parameters[0]->getName())->not->toContain('business');
    })->with(['customerIdsWithNumber', 'customerIdsMatchingNumber']);

    it('carries the number itself to the repository, untouched', function () {
        $number = PhoneNumbers::american();
        $asked = null;

        $this->phones->shouldReceive('ownerIdsWithNumber')->once()
            ->with(PhoneOwnerType::Customer, Mockery::capture($asked))
            ->andReturn([]);

        $this->phoneBook->customerIdsWithNumber($number);

        expect($asked)->toBe($number);
    });

    it('matches on the digits typed and drops everything else', function (string $typed, string $digits) {
        $fragment = null;

        $this->phones->shouldReceive('ownerIdsMatchingNumber')->once()
            ->with(PhoneOwnerType::Customer, Mockery::capture($fragment))
            ->andReturn([]);

        $this->phoneBook->customerIdsMatchingNumber($typed);

        expect($fragment)->toBeInstanceOf(PhoneNumberFragment::class)
            ->and($fragment->digits)->toBe($digits);
    })->with([
        'plain digits' => ['5512', '5512'],
        'formatted' => ['+52 (55) 12-34', '52551234'],
        'padded' => ['  5512  ', '5512'],
    ]);

    it('matches nobody and asks nothing when the fragment carries no digit at all', function (string $typed) {
        $this->phones->shouldNotReceive('ownerIdsMatchingNumber');

        expect($this->phoneBook->customerIdsMatchingNumber($typed))->toBe([]);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'letters' => 'abc',
        'punctuation' => '+- ()',
    ]);

    it('hands back nobody when no customer holds that number', function () {
        $this->phones->shouldReceive('ownerIdsWithNumber')->once()->andReturn([]);

        expect($this->phoneBook->customerIdsWithNumber(PhoneNumbers::mexican()))->toBe([]);
    });
});

describe('the rollback contract', function () {
    it('hands nothing back, so no use case response can cross the port', function () {
        $this->phones->shouldReceive('findForOwner')->once()->andReturnNull();
        $this->phones->shouldReceive('save')->once();

        expect(($this->replace)(PhoneNumbers::mexican()))->toBeNull();
    });

    it('never declares a use case response on the port', function () {
        $returnTypes = array_map(
            static fn (ReflectionMethod $method): string => (string) $method->getReturnType(),
            (new ReflectionClass(CustomerPhoneBook::class))->getMethods(),
        );

        expect($returnTypes)->not->toContain(UseCaseResponse::class)
            ->and($returnTypes)->not->toContain('?'.UseCaseResponse::class);
    });

    it('declares void on every write the port exposes', function (string $method) {
        expect((string) (new ReflectionMethod(CustomerPhoneBook::class, $method))->getReturnType())->toBe('void');
    })->with(['replaceForCustomer', 'removeForCustomer']);

    it('lets a domain failure out by throwing, so the surrounding transaction rolls back', function () {
        $failure = UseCaseFailed::with('phone_write_refused', DomainFailureKind::Conflict);

        $this->phones->shouldReceive('findForOwner')->once()->andReturnNull();
        $this->phones->shouldReceive('save')->once()->andThrow($failure);

        try {
            ($this->replace)(PhoneNumbers::mexican());
            $thrown = null;
        } catch (Throwable $escaped) {
            $thrown = $escaped;
        }

        expect($thrown)->toBe($failure);
    });

    it('lets an infrastructure error out untouched', function () {
        $bug = new RuntimeException('the phones table is gone');

        $this->phones->shouldReceive('findForOwner')->once()->andReturnNull();
        $this->phones->shouldReceive('save')->once()->andThrow($bug);

        expect(fn () => ($this->replace)(PhoneNumbers::mexican()))->toThrow($bug);
    });

    it('does not write when the read it depends on fails', function () {
        $bug = new RuntimeException('the read replica went away');

        $this->phones->shouldReceive('findForOwner')->once()->andThrow($bug);
        $this->phones->shouldNotReceive('save');

        expect(fn () => ($this->replace)(PhoneNumbers::mexican()))->toThrow($bug);
    });
});
