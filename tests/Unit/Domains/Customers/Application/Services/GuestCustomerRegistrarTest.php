<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\CustomerData;
use App\Domains\Customers\Application\Presenters\CustomerPresenter;
use App\Domains\Customers\Application\Services\GuestCustomerRegistrar;
use App\Domains\Customers\Application\Services\SubmittedPhoneNumber;
use App\Domains\Customers\Exceptions\InvalidCustomerEmail;
use App\Domains\Customers\Exceptions\InvalidCustomerName;
use App\Domains\Customers\Exceptions\InvalidCustomerPhone;
use App\Domains\Customers\Exceptions\InvalidGuestContact;
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
    $this->parser = FakePhoneNumberParser::accepting(PhoneNumbers::mexican(), PhoneNumbers::american());

    $this->registrar = new GuestCustomerRegistrar(
        $this->customers,
        $this->phones,
        new CustomerPresenter($this->phones, $this->addresses, $this->photos),
        new SubmittedPhoneNumber($this->parser),
        new FixedIdGenerator(CustomerFixtures::GENERATED_CUSTOMER_ID),
        new FakeClock(CustomerFixtures::now()),
        $this->transactions,
    );

    $this->register = fn (...$overrides): CustomerData => $this->registrar->register(
        FakeBusinessContext::BUSINESS_ID,
        CustomerFixtures::guestContact(...$overrides),
    );
});

describe('a guest nobody has on file', function () {
    it('enrolls the guest and answers with the record the booking will point at', function () {
        $data = ($this->register)();

        expect($data)->toBeInstanceOf(CustomerData::class)
            ->and($data->id)->toBe(CustomerFixtures::GENERATED_CUSTOMER_ID)
            ->and($data->id)->toMatch('/^[0-9a-f-]{36}$/i')
            ->and($data->name)->toBe(CustomerFixtures::NAME)
            ->and($data->email)->toBe(CustomerFixtures::EMAIL)
            ->and($data->phone?->e164())->toBe(PhoneNumbers::MX_E164)
            ->and($data->createdAt)->toEqual(CustomerFixtures::now());
    });

    it('saves the customer the identity and the clock handed it', function () {
        ($this->register)();

        expect($this->customers->saved)->toHaveCount(1)
            ->and($this->customers->saved[0]->id)->toBe(CustomerFixtures::GENERATED_CUSTOMER_ID)
            ->and($this->customers->saved[0]->createdAt)->toEqual(CustomerFixtures::now());
    });

    it('scopes the new customer to the business the booking was made at', function () {
        ($this->register)();

        expect($this->customers->saved[0]->businessId)->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('files the phone the parser made sense of', function () {
        ($this->register)();

        expect($this->phones->replacements)->toHaveCount(1)
            ->and($this->phones->replacements[0]['customerId'])->toBe(CustomerFixtures::GENERATED_CUSTOMER_ID)
            ->and($this->phones->replacements[0]['phone']?->e164())->toBe(PhoneNumbers::MX_E164);
    });

    it('enrolls a guest who left a phone and no email', function () {
        $data = ($this->register)(email: null);

        expect($data->email)->toBeNull()
            ->and($data->phone?->e164())->toBe(PhoneNumbers::MX_E164)
            ->and($this->customers->saved)->toHaveCount(1);
    });

    it('enrolls a guest who left an email and no phone', function () {
        $data = ($this->register)(phone: null);

        expect($data->email)->toBe(CustomerFixtures::EMAIL)
            ->and($data->phone)->toBeNull()
            ->and($this->phones->replacements[0]['phone'])->toBeNull();
    });

    it('enrols nobody with a birth date, because a booking form asks for none', function () {
        expect(($this->register)()->birthDate)->toBeNull();
    });

    it('writes the record and its phone as one unit of work', function () {
        ($this->register)();

        expect($this->transactions->runs())->toBe(1);
    });
});

describe('a guest already on file', function () {
    it('answers with the customer whose email the guest typed, saving nothing', function () {
        $this->customers->store(CustomerFixtures::customer());

        $data = ($this->register)();

        expect($data->id)->toBe(CustomerFixtures::CUSTOMER_ID)
            ->and($this->customers->saved)->toBe([])
            ->and($this->phones->replacements)->toBe([])
            ->and($this->transactions->runs())->toBe(0);
    });

    it('matches the email however the guest cased it', function () {
        $this->customers->store(CustomerFixtures::customer(email: 'ADA@example.com'));

        expect(($this->register)(email: 'ada@EXAMPLE.com')->id)->toBe(CustomerFixtures::CUSTOMER_ID);
    });

    it('answers with the customer holding the phone when the guest left no email', function () {
        $this->customers->store(CustomerFixtures::customer(email: null));
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::mexican());

        $data = ($this->register)(email: null);

        expect($data->id)->toBe(CustomerFixtures::CUSTOMER_ID)
            ->and($this->customers->saved)->toBe([])
            ->and($this->phones->numberLookups)->toBe([PhoneNumbers::MX_E164]);
    });

    it('looks the email up first and asks nothing about the phone once it matched', function () {
        $this->customers->store(CustomerFixtures::customer());
        $this->phones->store(CustomerFixtures::SECOND_CUSTOMER_ID, PhoneNumbers::mexican());

        expect(($this->register)()->id)->toBe(CustomerFixtures::CUSTOMER_ID)
            ->and($this->phones->numberLookups)->toBe([]);
    });

    it('falls back to the phone when the email it was given matches nobody', function () {
        $this->customers->store(CustomerFixtures::customer(email: 'grace@example.com'));
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::mexican());

        expect(($this->register)(email: 'ada@example.com')->id)->toBe(CustomerFixtures::CUSTOMER_ID)
            ->and($this->customers->saved)->toBe([]);
    });

    it('enrols a new customer when neither the email nor the phone matches anyone', function () {
        $this->customers->store(CustomerFixtures::customer(email: 'grace@example.com'));
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::american());

        expect(($this->register)()->id)->toBe(CustomerFixtures::GENERATED_CUSTOMER_ID)
            ->and($this->customers->saved)->toHaveCount(1);
    });

    it('takes the oldest record when the phone is held by more than one customer', function () {
        $this->customers->store(
            CustomerFixtures::customer(id: CustomerFixtures::CUSTOMER_ID, email: null),
            CustomerFixtures::customer(id: CustomerFixtures::SECOND_CUSTOMER_ID, email: null),
        );
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::mexican());
        $this->phones->store(CustomerFixtures::SECOND_CUSTOMER_ID, PhoneNumbers::mexican());

        expect(($this->register)(email: null)->id)->toBe(CustomerFixtures::CUSTOMER_ID);
    });
});

describe('leaving a matched record exactly as it was', function () {
    beforeEach(function () {
        $this->stored = CustomerFixtures::customer();
        $this->customers->store($this->stored);
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::mexican());
    });

    it('never rewrites the name of a customer the email matched', function () {
        ($this->register)(name: 'Somebody Else');

        expect($this->stored->name())->toBe(CustomerFixtures::NAME)
            ->and($this->customers->saved)->toBe([]);
    });

    it('never replaces the phone of a customer the email matched, however different the one the guest typed', function () {
        $data = ($this->register)(phone: CustomerFixtures::phoneInput(
            countryCode: 'US',
            nationalNumber: PhoneNumbers::US_NATIONAL_NUMBER,
        ));

        expect($this->phones->replacements)->toBe([])
            ->and($data->phone?->e164())->toBe(PhoneNumbers::MX_E164);
    });

    it('never rewrites the notes of a customer the email matched', function () {
        ($this->register)(notes: 'Typed by a stranger.');

        expect($this->stored->notes())->toBe(CustomerFixtures::NOTES);
    });

    it('never rewrites the email of a customer the phone matched', function () {
        ($this->register)(email: null);

        expect($this->stored->email()?->value)->toBe(CustomerFixtures::EMAIL)
            ->and($this->customers->saved)->toBe([]);
    });

    it('opens no transaction at all when it matched somebody', function () {
        ($this->register)();

        expect($this->transactions->runs())->toBe(0);
    });
});

describe('the business the guest booked at', function () {
    it('never matches a customer of another business carrying the same email', function () {
        $this->customers->store(CustomerFixtures::customer(businessId: CustomerFixtures::OTHER_BUSINESS_ID));

        expect(($this->register)()->id)->toBe(CustomerFixtures::GENERATED_CUSTOMER_ID)
            ->and($this->customers->saved[0]->businessId)->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('never matches a customer of another business holding the same phone', function () {
        $this->customers->store(CustomerFixtures::customer(
            businessId: CustomerFixtures::OTHER_BUSINESS_ID,
            email: null,
        ));
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::mexican());

        expect(($this->register)(email: null)->id)->toBe(CustomerFixtures::GENERATED_CUSTOMER_ID);
    });

    it('asks every port about the business it was handed and about no other', function () {
        ($this->register)();

        expect(array_unique($this->customers->businessIdsSeen))->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('registers at whichever business the caller named', function () {
        $data = $this->registrar->register(
            CustomerFixtures::OTHER_BUSINESS_ID,
            CustomerFixtures::guestContact(),
        );

        expect($data->id)->toBe(CustomerFixtures::GENERATED_CUSTOMER_ID)
            ->and($this->customers->saved[0]->businessId)->toBe(CustomerFixtures::OTHER_BUSINESS_ID);
    });
});

describe('refusing a contact', function () {
    it('refuses a guest nobody could be reached at, before it looks anybody up', function () {
        expect(fn () => ($this->register)(email: null, phone: null))->toThrow(InvalidGuestContact::class)
            ->and($this->customers->saved)->toBe([])
            ->and($this->customers->businessIdsSeen)->toBe([])
            ->and($this->transactions->runs())->toBe(0);
    });

    it('refuses what the contact itself refuses, writing nothing', function (array $overrides, string $exception) {
        expect(fn () => ($this->register)(...$overrides))->toThrow($exception)
            ->and($this->customers->saved)->toBe([])
            ->and($this->phones->replacements)->toBe([]);
    })->with([
        'a blank name' => [['name' => '   '], InvalidCustomerName::class],
        'a malformed email' => [['email' => 'nope'], InvalidCustomerEmail::class],
        'a phone with no number' => [
            ['phone' => CustomerFixtures::phoneInput(nationalNumber: '   ')],
            InvalidCustomerPhone::class,
        ],
    ]);

    it('refuses a number the parser cannot read, writing nothing', function () {
        expect(fn () => ($this->register)(
            email: null,
            phone: CustomerFixtures::phoneInput(nationalNumber: '9999999999'),
        ))->toThrow(InvalidCustomerPhone::class)
            ->and($this->customers->saved)->toBe([])
            ->and($this->phones->replacements)->toBe([]);
    });

    it('refuses a country the platform does not dial, writing nothing', function () {
        expect(fn () => ($this->register)(
            email: null,
            phone: CustomerFixtures::phoneInput(countryCode: 'FR', nationalNumber: '612345678'),
        ))->toThrow(InvalidCustomerPhone::class)
            ->and($this->customers->saved)->toBe([]);
    });
});
