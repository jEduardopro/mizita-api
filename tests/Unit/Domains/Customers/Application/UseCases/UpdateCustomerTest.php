<?php

declare(strict_types=1);

use App\Domains\Addresses\Exceptions\AddressCityCannotBeCleared;
use App\Domains\Addresses\Exceptions\AddressPostalCodeCannotBeCleared;
use App\Domains\Addresses\Exceptions\InvalidAddressStreet;
use App\Domains\Addresses\Exceptions\UnsupportedCountry;
use App\Domains\Customers\Application\Dtos\CustomerData;
use App\Domains\Customers\Application\Presenters\CustomerPresenter;
use App\Domains\Customers\Application\Services\ContactUniqueness;
use App\Domains\Customers\Application\Services\SubmittedPhoneNumber;
use App\Domains\Customers\Application\UseCases\UpdateCustomer;
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
use Tests\Support\PhoneNumbers;

beforeEach(function () {
    $this->transactions = new FakeTransactionManager;
    $this->journal = new CustomerJournal($this->transactions);
    $this->customers = new FakeCustomerRepository($this->journal);
    $this->phones = new FakeCustomerPhoneBook($this->journal);
    $this->addresses = new FakeCustomerAddressBook($this->journal);
    $this->photos = new FakeCustomerPhotos($this->journal);
    $this->parser = FakePhoneNumberParser::accepting(PhoneNumbers::mexican(), PhoneNumbers::american());
    $this->clock = new FakeClock(CustomerFixtures::now());

    $this->build = fn (?FakeBusinessContext $business = null): UpdateCustomer => new UpdateCustomer(
        $this->customers,
        $this->phones,
        $this->addresses,
        new CustomerPresenter($this->phones, $this->addresses, $this->photos),
        new SubmittedPhoneNumber($this->parser),
        new ContactUniqueness($this->customers, $this->phones),
        $this->clock,
        $business ?? new FakeBusinessContext,
        $this->transactions,
    );

    $this->useCase = ($this->build)();

    $this->update = fn (...$overrides) => $this->useCase->handle(CustomerFixtures::updateInput(...$overrides));

    $this->customers->store(CustomerFixtures::customer());
});

describe('updating a customer', function () {
    it('answers with the customer as it now stands', function () {
        $data = ($this->update)(
            name: 'Grace Hopper',
            email: 'grace@example.com',
            birthDate: '1906-12-09',
            notes: 'Llega temprano.',
        )->value();

        expect($data)->toBeInstanceOf(CustomerData::class)
            ->and($data->id)->toBe(CustomerFixtures::CUSTOMER_ID)
            ->and($data->name)->toBe('Grace Hopper')
            ->and($data->email)->toBe('grace@example.com')
            ->and($data->phone?->e164())->toBe(PhoneNumbers::MX_E164)
            ->and($data->birthDate)->toEqual(CustomerFixtures::birthDate('1906-12-09'))
            ->and($data->notes)->toBe('Llega temprano.')
            ->and($data->address?->street)->toBe(CustomerFixtures::STREET)
            ->and($data->createdAt)->toEqual(CustomerFixtures::now());
    });

    it('saves the customer it found, keeping the identity and the day it was created', function () {
        ($this->update)(name: 'Grace Hopper');

        expect($this->customers->saved)->toHaveCount(1)
            ->and($this->customers->saved[0]->id)->toBe(CustomerFixtures::CUSTOMER_ID)
            ->and($this->customers->saved[0]->name())->toBe('Grace Hopper')
            ->and($this->customers->saved[0]->createdAt)->toEqual(CustomerFixtures::now());
    });

    it('reads the customer before the transaction and writes everything inside it', function () {
        ($this->update)();

        expect($this->transactions->runs())->toBe(1)
            ->and($this->journal->entries)->toBe([
                'customers.find',
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
                'customers.find',
                'phones.forCustomer',
                'addresses.forCustomer',
                'photos.forCustomer',
            ]);
    });

    it('empties what the caller left empty', function () {
        $data = ($this->update)(email: null, birthDate: null, notes: null)->value();

        expect($data->email)->toBeNull()
            ->and($data->birthDate)->toBeNull()
            ->and($data->notes)->toBeNull()
            ->and($this->customers->saved[0]->email())->toBeNull()
            ->and($this->customers->saved[0]->notes())->toBeNull();
    });

    it('removes the phone when the caller sent none', function () {
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::mexican());

        $data = ($this->update)(phone: null)->value();

        expect($this->phones->replacements)->toBe([[
            'customerId' => CustomerFixtures::CUSTOMER_ID,
            'phone' => null,
        ]])->and($data->phone)->toBeNull();
    });

    it('replaces the phone with the new one', function () {
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::mexican());

        $data = ($this->update)(phone: CustomerFixtures::phoneInput(
            countryCode: 'US',
            nationalNumber: PhoneNumbers::US_NATIONAL_NUMBER,
        ))->value();

        expect($data->phone?->e164())->toBe(PhoneNumbers::US_E164);
    });

    it('trims what the caller padded', function () {
        $data = ($this->update)(name: '  Grace Hopper  ', notes: '   ')->value();

        expect($data->name)->toBe('Grace Hopper')
            ->and($data->notes)->toBeNull();
    });
});

describe('the contacts that must stay unique', function () {
    it('lets a customer save the email it already carries', function () {
        $response = ($this->update)();

        expect($response->succeeded())->toBeTrue()
            ->and($this->customers->emailChecks)->toBe([[
                'businessId' => FakeBusinessContext::BUSINESS_ID,
                'email' => CustomerFixtures::EMAIL,
                'exceptId' => CustomerFixtures::CUSTOMER_ID,
            ]]);
    });

    it('lets a customer save the number it already holds', function () {
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::mexican());

        $response = ($this->update)();

        expect($response->succeeded())->toBeTrue()
            ->and($this->customers->membershipChecks)->toBe([[
                'businessId' => FakeBusinessContext::BUSINESS_ID,
                'customerIds' => [CustomerFixtures::CUSTOMER_ID],
                'exceptId' => CustomerFixtures::CUSTOMER_ID,
            ]]);
    });

    it('refuses an email another customer of the business already carries', function () {
        $this->customers->store(CustomerFixtures::customer(
            id: CustomerFixtures::SECOND_CUSTOMER_ID,
            email: 'grace@example.com',
        ));

        $response = ($this->update)(email: 'grace@example.com');

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('customer_email_taken')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->customers->saved)->toBe([])
            ->and($this->phones->replacements)->toBe([])
            ->and($this->addresses->calls)->toBe([]);
    });

    it('refuses a number another customer of the business already holds', function () {
        $this->customers->store(CustomerFixtures::customer(
            id: CustomerFixtures::SECOND_CUSTOMER_ID,
            email: 'grace@example.com',
        ));
        $this->phones->store(CustomerFixtures::SECOND_CUSTOMER_ID, PhoneNumbers::mexican());

        $response = ($this->update)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('customer_phone_taken')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->customers->saved)->toBe([]);
    });

    it('accepts contacts only another business holds', function () {
        $this->customers->store(CustomerFixtures::customer(
            id: CustomerFixtures::FOREIGN_CUSTOMER_ID,
            businessId: CustomerFixtures::OTHER_BUSINESS_ID,
        ));
        $this->phones->store(CustomerFixtures::FOREIGN_CUSTOMER_ID, PhoneNumbers::mexican());

        expect(($this->update)()->succeeded())->toBeTrue();
    });
});

describe('the address it may or may not write', function () {
    it('leaves the stored address untouched when the caller sent none', function () {
        $this->addresses->store(CustomerFixtures::CUSTOMER_ID, CustomerFixtures::address());

        $data = ($this->update)(address: null)->value();

        expect($this->addresses->calls)->toBe([])
            ->and($data->address?->street)->toBe(CustomerFixtures::STREET);
    });

    it('files nothing for a blank street when the customer had no address', function () {
        $data = ($this->update)(address: CustomerFixtures::address(
            street: '  ',
            city: null,
            stateId: null,
            postalCode: null,
        ))->value();

        expect($this->addresses->calls)->toHaveCount(1)
            ->and($this->addresses->replacements)->toBe([])
            ->and($data->address)->toBeNull();
    });

    it('answers with whatever the address book refuses', function (Throwable $failure, string $code, DomainFailureKind $kind) {
        $this->addresses->store(CustomerFixtures::CUSTOMER_ID, CustomerFixtures::address());
        $this->addresses->failingOnReplace($failure);

        $response = ($this->update)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe($kind)
            ->and($this->transactions->runs())->toBe(1);
    })->with([
        'a city that may no longer be emptied' => [
            AddressCityCannotBeCleared::alreadySet(),
            'address_city_cannot_be_cleared',
            DomainFailureKind::Conflict,
        ],
        'a postal code that may no longer be emptied' => [
            AddressPostalCodeCannotBeCleared::alreadySet(),
            'address_postal_code_cannot_be_cleared',
            DomainFailureKind::Conflict,
        ],
        'a street emptied on a filed address' => [
            InvalidAddressStreet::empty(),
            'invalid_address_street',
            DomainFailureKind::Invalid,
        ],
        'a country we do not serve' => [
            UnsupportedCountry::withCode('ZZ'),
            'unsupported_country',
            DomainFailureKind::Invalid,
        ],
    ]);
});

describe('the customer it may reach', function () {
    it('reads and writes under the business in context, never one a caller could name', function () {
        ($this->update)();

        expect(array_unique($this->customers->businessIdsSeen))->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('does not update a customer that belongs to another business', function () {
        $response = ($this->build)(new FakeBusinessContext(CustomerFixtures::OTHER_BUSINESS_ID))
            ->handle(CustomerFixtures::updateInput());

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('customer_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->customers->saved)->toBe([])
            ->and($this->transactions->runs())->toBe(0);
    });

    it('answers not found for a customer nobody has', function () {
        $response = ($this->update)(customerId: CustomerFixtures::SECOND_CUSTOMER_ID);

        expect($response->error()->code)->toBe('customer_not_found')
            ->and($this->transactions->runs())->toBe(0);
    });

    it('answers not found for an identifier that cannot be a customer, without asking the repository', function () {
        $response = ($this->update)(customerId: 'not-a-uuid');

        expect($response->error()->code)->toBe('customer_not_found')
            ->and($this->journal->entries)->toBe([])
            ->and($this->transactions->runs())->toBe(0);
    });
});

describe('refusing to update', function () {
    it('refuses what the input itself refuses, without reading or opening a transaction', function (array $overrides, string $code) {
        $response = ($this->update)(...$overrides);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->journal->entries)->toBe([])
            ->and($this->transactions->runs())->toBe(0);
    })->with([
        'a blank name' => [['name' => '   '], 'invalid_customer_name'],
        'a name too long' => [['name' => str_repeat('a', 121)], 'invalid_customer_name'],
        'a malformed email' => [['email' => 'not-an-email'], 'invalid_customer_email'],
        'a birth date in another format' => [['birthDate' => '1990/05/04'], 'invalid_customer_birth_date'],
        'notes too long' => [['notes' => str_repeat('a', 2001)], 'invalid_customer_notes'],
        'a country code of one letter' => [
            ['phone' => CustomerFixtures::phoneInput(countryCode: 'M')],
            'invalid_customer_phone',
        ],
    ]);

    it('refuses a birth date the clock says has not happened yet', function () {
        $response = ($this->update)(birthDate: '2030-06-01');

        expect($response->error()->code)->toBe('invalid_customer_birth_date')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->customers->saved)->toBe([]);
    });

    it('refuses a number the parser cannot read', function () {
        $response = ($this->update)(phone: CustomerFixtures::phoneInput(nationalNumber: '1'));

        expect($response->error()->code)->toBe('invalid_customer_phone')
            ->and($this->customers->saved)->toBe([]);
    });
});
