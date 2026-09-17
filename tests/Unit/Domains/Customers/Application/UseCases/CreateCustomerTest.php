<?php

declare(strict_types=1);

use App\Domains\Addresses\Exceptions\InvalidAddressStreet;
use App\Domains\Addresses\Exceptions\UnknownState;
use App\Domains\Addresses\Exceptions\UnsupportedCountry;
use App\Domains\Customers\Application\Dtos\CustomerData;
use App\Domains\Customers\Application\Presenters\CustomerPresenter;
use App\Domains\Customers\Application\Services\ContactUniqueness;
use App\Domains\Customers\Application\Services\SubmittedPhoneNumber;
use App\Domains\Customers\Application\UseCases\CreateCustomer;
use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\CustomerEmailAlreadyTaken;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Customers\CustomerFixtures;
use Tests\Support\Customers\CustomerJournal;
use Tests\Support\Customers\FakeCustomerAddressBook;
use Tests\Support\Customers\FakeCustomerPhoneBook;
use Tests\Support\Customers\FakeCustomerPhotos;
use Tests\Support\Customers\FakeCustomerRepository;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FakePhoneNumberParser;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;
use Tests\Support\PhoneNumbers;

beforeEach(function () {
    $this->transactions = new FakeTransactionManager;
    $this->journal = new CustomerJournal($this->transactions);
    $this->customers = new FakeCustomerRepository($this->journal);
    $this->phones = new FakeCustomerPhoneBook($this->journal);
    $this->addresses = new FakeCustomerAddressBook($this->journal);
    $this->photos = new FakeCustomerPhotos($this->journal);
    $this->parser = FakePhoneNumberParser::accepting(PhoneNumbers::mexican());
    $this->clock = new FakeClock(CustomerFixtures::now());

    $this->build = fn (?FakeBusinessContext $business = null): CreateCustomer => new CreateCustomer(
        $this->customers,
        $this->phones,
        $this->addresses,
        new CustomerPresenter($this->phones, $this->addresses, $this->photos),
        new SubmittedPhoneNumber($this->parser),
        new ContactUniqueness($this->customers, $this->phones),
        new FixedIdGenerator(CustomerFixtures::GENERATED_CUSTOMER_ID),
        $this->clock,
        $business ?? new FakeBusinessContext,
        $this->transactions,
    );

    $this->useCase = ($this->build)();

    $this->create = fn (...$overrides) => $this->useCase->handle(CustomerFixtures::createInput(...$overrides));
});

describe('creating a customer', function () {
    it('answers with every field the client reads', function () {
        $data = ($this->create)()->value();

        expect($data)->toBeInstanceOf(CustomerData::class)
            ->and($data->id)->toBe(CustomerFixtures::GENERATED_CUSTOMER_ID)
            ->and($data->name)->toBe(CustomerFixtures::NAME)
            ->and($data->email)->toBe(CustomerFixtures::EMAIL)
            ->and($data->phone?->e164())->toBe(PhoneNumbers::MX_E164)
            ->and($data->birthDate)->toEqual(CustomerFixtures::birthDate())
            ->and($data->notes)->toBe(CustomerFixtures::NOTES)
            ->and($data->address?->street)->toBe(CustomerFixtures::STREET)
            ->and($data->address?->city)->toBe(CustomerFixtures::CITY)
            ->and($data->address?->stateId)->toBe(CustomerFixtures::STATE_ID)
            ->and($data->address?->postalCode)->toBe(CustomerFixtures::POSTAL_CODE)
            ->and($data->address?->countryCode)->toBe(CustomerFixtures::COUNTRY_CODE)
            ->and($data->createdAt)->toEqual(CustomerFixtures::now());
    });

    it('saves the customer the identity and the clock handed it', function () {
        ($this->create)();

        expect($this->customers->saved)->toHaveCount(1);

        $saved = $this->customers->saved[0];

        expect($saved)->toBeInstanceOf(Customer::class)
            ->and($saved->id)->toBe(CustomerFixtures::GENERATED_CUSTOMER_ID)
            ->and($saved->name())->toBe(CustomerFixtures::NAME)
            ->and($saved->email()?->value)->toBe(CustomerFixtures::EMAIL)
            ->and($saved->birthDate())->toEqual(CustomerFixtures::birthDate())
            ->and($saved->notes())->toBe(CustomerFixtures::NOTES)
            ->and($saved->createdAt)->toEqual(CustomerFixtures::now());
    });

    it('attaches the phone and the address to the customer it just created', function () {
        ($this->create)();

        expect($this->phones->replacements)->toHaveCount(1)
            ->and($this->phones->replacements[0]['customerId'])->toBe(CustomerFixtures::GENERATED_CUSTOMER_ID)
            ->and($this->phones->replacements[0]['phone']?->e164())->toBe(PhoneNumbers::MX_E164)
            ->and($this->addresses->replacements)->toHaveCount(1)
            ->and($this->addresses->replacements[0]['customerId'])->toBe(CustomerFixtures::GENERATED_CUSTOMER_ID)
            ->and($this->addresses->replacements[0]['address']->street)->toBe(CustomerFixtures::STREET);
    });

    it('writes the customer, the phone and the address in one transaction, and reads back outside it', function () {
        ($this->create)();

        expect($this->transactions->runs())->toBe(1)
            ->and($this->journal->entries)->toBe([
                'customers.existsByEmail',
                'phones.idsWithNumber',
                'customers.save',
                'phones.replace',
                'addresses.replace',
                'phones.forCustomer',
                'addresses.forCustomer',
                'photos.forCustomer',
            ])
            ->and($this->journal->outsideTransaction)->toBe([
                'phones.forCustomer',
                'addresses.forCustomer',
                'photos.forCustomer',
            ]);
    });

    it('creates a customer that carries nothing but a name', function () {
        $data = ($this->create)(
            email: null,
            phone: null,
            birthDate: null,
            notes: null,
            address: null,
        )->value();

        expect($data->name)->toBe(CustomerFixtures::NAME)
            ->and($data->email)->toBeNull()
            ->and($data->phone)->toBeNull()
            ->and($data->birthDate)->toBeNull()
            ->and($data->notes)->toBeNull()
            ->and($data->address)->toBeNull()
            ->and($this->customers->saved)->toHaveCount(1);
    });

    it('tells the phone book there is no number rather than leaving it unasked', function () {
        ($this->create)(phone: null);

        expect($this->phones->replacements)->toBe([[
            'customerId' => CustomerFixtures::GENERATED_CUSTOMER_ID,
            'phone' => null,
        ]]);
    });

    it('trims what the caller padded', function () {
        $data = ($this->create)(name: '  Ada Lovelace  ', notes: '   ')->value();

        expect($data->name)->toBe(CustomerFixtures::NAME)
            ->and($data->notes)->toBeNull()
            ->and($this->customers->saved[0]->name())->toBe(CustomerFixtures::NAME);
    });
});

describe('the address it may or may not write', function () {
    it('touches no address at all when the caller sent none', function () {
        ($this->create)(address: null);

        expect($this->addresses->calls)->toBe([])
            ->and($this->addresses->replacements)->toBe([]);
    });

    it('hands a blank street to the address book, which files nothing for it', function () {
        $data = ($this->create)(address: CustomerFixtures::address(
            street: '   ',
            city: null,
            stateId: null,
            postalCode: null,
        ))->value();

        expect($this->addresses->calls)->toHaveCount(1)
            ->and($this->addresses->replacements)->toBe([])
            ->and($data->address)->toBeNull();
    });

    it('files an address that carries a street and nothing else', function () {
        $data = ($this->create)(address: CustomerFixtures::address(
            city: null,
            stateId: null,
            postalCode: null,
        ))->value();

        expect($data->address?->street)->toBe(CustomerFixtures::STREET)
            ->and($data->address?->city)->toBeNull()
            ->and($data->address?->postalCode)->toBeNull();
    });

    it('answers with whatever the address book refuses', function (Throwable $failure, string $code, DomainFailureKind $kind) {
        $this->addresses->failingOnReplace($failure);

        $response = ($this->create)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe($kind)
            ->and($this->transactions->runs())->toBe(1);
    })->with([
        'a country we do not serve' => [
            UnsupportedCountry::withCode('ZZ'),
            'unsupported_country',
            DomainFailureKind::Invalid,
        ],
        'a state nobody has' => [
            UnknownState::withId(CustomerFixtures::STATE_ID),
            'unknown_state',
            DomainFailureKind::Invalid,
        ],
        'a street too long to file' => [
            InvalidAddressStreet::tooLong(),
            'invalid_address_street',
            DomainFailureKind::Invalid,
        ],
    ]);
});

describe('the business it belongs to', function () {
    it('scopes the customer to the business in context, never to one a caller could name', function () {
        ($this->create)();

        expect($this->customers->saved[0]->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and(array_unique($this->customers->businessIdsSeen))->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('creates under whatever business the context names, and asks its guards about that one', function () {
        ($this->build)(new FakeBusinessContext(CustomerFixtures::OTHER_BUSINESS_ID))
            ->handle(CustomerFixtures::createInput());

        expect($this->customers->saved[0]->businessId)->toBe(CustomerFixtures::OTHER_BUSINESS_ID)
            ->and($this->customers->emailChecks[0]['businessId'])->toBe(CustomerFixtures::OTHER_BUSINESS_ID);
    });
});

describe('the contacts that must stay unique', function () {
    it('refuses an email another customer of the business already carries', function (string $stored, string $submitted) {
        $this->customers->store(CustomerFixtures::customer(email: $stored));

        $response = ($this->create)(email: $submitted);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('customer_email_taken')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->customers->saved)->toBe([])
            ->and($this->phones->replacements)->toBe([])
            ->and($this->addresses->calls)->toBe([]);
    })->with([
        'the same email' => [CustomerFixtures::EMAIL, CustomerFixtures::EMAIL],
        'the same email in another case' => [CustomerFixtures::EMAIL, 'ADA@EXAMPLE.COM'],
    ]);

    it('accepts an email only another business carries', function () {
        $this->customers->store(CustomerFixtures::customer(businessId: CustomerFixtures::OTHER_BUSINESS_ID));

        expect(($this->create)()->succeeded())->toBeTrue();
    });

    it('refuses a number another customer of the business already holds', function () {
        $this->customers->store(CustomerFixtures::customer(email: 'grace@example.com'));
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::mexican());

        $response = ($this->create)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('customer_phone_taken')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->customers->saved)->toBe([])
            ->and($this->addresses->calls)->toBe([]);
    });

    it('accepts a number only a customer of another business holds', function () {
        $this->customers->store(CustomerFixtures::customer(
            id: CustomerFixtures::FOREIGN_CUSTOMER_ID,
            businessId: CustomerFixtures::OTHER_BUSINESS_ID,
            email: 'grace@example.com',
        ));
        $this->phones->store(CustomerFixtures::FOREIGN_CUSTOMER_ID, PhoneNumbers::mexican());

        expect(($this->create)()->succeeded())->toBeTrue();
    });
});

describe('refusing to create', function () {
    it('refuses what the input itself refuses, without opening a transaction', function (array $overrides, string $code) {
        $response = ($this->create)(...$overrides);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->transactions->runs())->toBe(0)
            ->and($this->journal->entries)->toBe([])
            ->and($this->customers->saved)->toBe([]);
    })->with([
        'a blank name' => [['name' => '   '], 'invalid_customer_name'],
        'a name of nothing but a tab' => [['name' => "\t"], 'invalid_customer_name'],
        'a name too long' => [['name' => str_repeat('a', 121)], 'invalid_customer_name'],
        'a malformed email' => [['email' => 'not-an-email'], 'invalid_customer_email'],
        'an email too long' => [['email' => str_repeat('a', 246).'@example.com'], 'invalid_customer_email'],
        'a birth date in another format' => [['birthDate' => '04-05-1990'], 'invalid_customer_birth_date'],
        'a birth date that never was' => [['birthDate' => '1990-02-31'], 'invalid_customer_birth_date'],
        'notes too long' => [['notes' => str_repeat('a', 2001)], 'invalid_customer_notes'],
        'a country code of one letter' => [
            ['phone' => CustomerFixtures::phoneInput(countryCode: 'M')],
            'invalid_customer_phone',
        ],
        'a phone with no digits at all' => [
            ['phone' => CustomerFixtures::phoneInput(nationalNumber: '   ')],
            'invalid_customer_phone',
        ],
    ]);

    it('refuses a country the platform does not serve before it asks any guard', function () {
        $response = ($this->create)(phone: CustomerFixtures::phoneInput(countryCode: 'ZZ'));

        expect($response->error()->code)->toBe('invalid_customer_phone')
            ->and($this->customers->emailChecks)->toBe([])
            ->and($this->customers->saved)->toBe([]);
    });

    it('refuses a number the parser cannot read', function () {
        $response = ($this->create)(phone: CustomerFixtures::phoneInput(nationalNumber: '1'));

        expect($response->error()->code)->toBe('invalid_customer_phone')
            ->and($this->customers->saved)->toBe([]);
    });

    it('refuses a birth date the clock says has not happened yet', function (string $birthDate) {
        $response = ($this->create)(birthDate: $birthDate);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_customer_birth_date')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->customers->saved)->toBe([]);
    })->with([
        'the day after the clock' => '2026-01-02',
        'years ahead' => '2030-06-01',
    ]);

    it('refuses a birth date from before the platform believes anyone was born', function () {
        expect(($this->create)(birthDate: '1899-12-31')->error()->code)->toBe('invalid_customer_birth_date');
    });

    it('accepts a birth date of the very day the clock reads', function () {
        expect(($this->create)(birthDate: '2026-01-01')->succeeded())->toBeTrue();
    });

    it('writes neither phone nor address when the save itself fails', function () {
        $this->customers->failingOnSave(CustomerEmailAlreadyTaken::for(CustomerFixtures::EMAIL));

        $response = ($this->create)();

        expect($response->error()->code)->toBe('customer_email_taken')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->phones->replacements)->toBe([])
            ->and($this->addresses->calls)->toBe([]);
    });
});
