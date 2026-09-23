<?php

declare(strict_types=1);

use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\Exceptions\InvalidGuestAddress;
use App\Domains\Appointments\Exceptions\InvalidGuestName;
use App\Domains\Appointments\Infrastructure\Gateways\CustomersCustomerDirectory;
use App\Domains\Appointments\ValueObjects\CustomerPhoneSnapshot;
use App\Domains\Appointments\ValueObjects\CustomerSnapshot;
use App\Domains\Appointments\ValueObjects\GuestAddress;
use App\Domains\Appointments\ValueObjects\GuestContact;
use App\Domains\Appointments\ValueObjects\GuestPhone;
use App\Domains\Customers\Application\Presenters\CustomerPresenter;
use App\Domains\Customers\Application\Services\GuestCustomerRegistrar;
use App\Domains\Customers\Application\Services\SubmittedPhoneNumber;
use App\Domains\Customers\Exceptions\InvalidCustomerAddress;
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

    $this->directory = new CustomersCustomerDirectory(
        $this->customers,
        new GuestCustomerRegistrar(
            $this->customers,
            $this->phones,
            $this->addresses,
            new CustomerPresenter($this->phones, $this->addresses, $this->photos),
            new SubmittedPhoneNumber(FakePhoneNumberParser::accepting(PhoneNumbers::mexican())),
            new FixedIdGenerator(CustomerFixtures::GENERATED_CUSTOMER_ID),
            new FakeClock(CustomerFixtures::now()),
            $this->transactions,
        ),
        $this->phones,
    );

    $this->describeMany = fn (array $ids): array => $this->directory->describeMany(
        FakeBusinessContext::BUSINESS_ID,
        $ids,
    );
});

describe('describing a batch of customers', function () {
    beforeEach(function () {
        $this->customers->store(
            CustomerFixtures::customer(),
            CustomerFixtures::customer(id: CustomerFixtures::SECOND_CUSTOMER_ID, name: 'Grace Hopper', email: null),
            CustomerFixtures::customer(id: CustomerFixtures::THIRD_CUSTOMER_ID, name: 'Katherine Johnson'),
        );
    });

    it('asks the repository once and the phone book once, however many customers it was given', function () {
        ($this->describeMany)([
            CustomerFixtures::CUSTOMER_ID,
            CustomerFixtures::SECOND_CUSTOMER_ID,
            CustomerFixtures::THIRD_CUSTOMER_ID,
        ]);

        expect($this->customers->batchLookups)->toHaveCount(1)
            ->and($this->phones->batchReads)->toHaveCount(1)
            ->and($this->phones->reads)->toBe([])
            ->and($this->journal->entries)->toBe([
                'customers.findManyIncludingArchived',
                'phones.forCustomers',
            ]);
    });

    it('keys every snapshot by the customer uuid', function () {
        $snapshots = ($this->describeMany)([
            CustomerFixtures::CUSTOMER_ID,
            CustomerFixtures::SECOND_CUSTOMER_ID,
        ]);

        expect(array_keys($snapshots))->toBe([
            CustomerFixtures::CUSTOMER_ID,
            CustomerFixtures::SECOND_CUSTOMER_ID,
        ])
            ->and($snapshots[CustomerFixtures::CUSTOMER_ID])->toBeInstanceOf(CustomerSnapshot::class)
            ->and($snapshots[CustomerFixtures::CUSTOMER_ID]->id)->toBe(CustomerFixtures::CUSTOMER_ID)
            ->and($snapshots[CustomerFixtures::CUSTOMER_ID]->name)->toBe(CustomerFixtures::NAME)
            ->and($snapshots[CustomerFixtures::CUSTOMER_ID]->email)->toBe(CustomerFixtures::EMAIL)
            ->and($snapshots[CustomerFixtures::SECOND_CUSTOMER_ID]->email)->toBeNull();
    });

    it('turns the number the phone book holds into a snapshot the appointment can render', function () {
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::mexican());

        $phone = ($this->describeMany)([CustomerFixtures::CUSTOMER_ID])[CustomerFixtures::CUSTOMER_ID]->phone;

        expect($phone)->toBeInstanceOf(CustomerPhoneSnapshot::class)
            ->and($phone?->countryCode)->toBe('MX')
            ->and($phone?->nationalNumber)->toBe(PhoneNumbers::MX_NATIONAL_NUMBER);
    });

    it('carries no phone for a customer the phone book knows nothing about', function () {
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::mexican());

        $snapshots = ($this->describeMany)([
            CustomerFixtures::CUSTOMER_ID,
            CustomerFixtures::SECOND_CUSTOMER_ID,
        ]);

        expect($snapshots[CustomerFixtures::SECOND_CUSTOMER_ID]->phone)->toBeNull()
            ->and($snapshots[CustomerFixtures::CUSTOMER_ID]->phone)->not->toBeNull();
    });

    it('asks the repository about each customer once when the same id repeats', function () {
        ($this->describeMany)([
            CustomerFixtures::CUSTOMER_ID,
            CustomerFixtures::CUSTOMER_ID,
            CustomerFixtures::SECOND_CUSTOMER_ID,
        ]);

        expect($this->customers->batchLookups[0]['ids'])->toBe([
            CustomerFixtures::CUSTOMER_ID,
            CustomerFixtures::SECOND_CUSTOMER_ID,
        ]);
    });

    it('leaves an unknown id out instead of refusing the whole batch', function () {
        $snapshots = ($this->describeMany)([
            CustomerFixtures::CUSTOMER_ID,
            CustomerFixtures::FOREIGN_CUSTOMER_ID,
        ]);

        expect(array_keys($snapshots))->toBe([CustomerFixtures::CUSTOMER_ID]);
    });

    it('asks no phone at all when it recognised none of the ids', function () {
        expect(($this->describeMany)([CustomerFixtures::FOREIGN_CUSTOMER_ID]))->toBe([])
            ->and($this->phones->batchReads)->toBe([]);
    });

    it('still describes a customer that was archived, so a past appointment keeps its name', function () {
        $this->customers->delete(FakeBusinessContext::BUSINESS_ID, CustomerFixtures::CUSTOMER_ID);

        $snapshots = ($this->describeMany)([CustomerFixtures::CUSTOMER_ID]);

        expect($snapshots[CustomerFixtures::CUSTOMER_ID]->name)->toBe(CustomerFixtures::NAME);
    });

    it('sees nothing of a customer belonging to another business', function () {
        $this->customers->store(CustomerFixtures::customer(
            id: CustomerFixtures::FOREIGN_CUSTOMER_ID,
            businessId: CustomerFixtures::OTHER_BUSINESS_ID,
        ));

        expect(($this->describeMany)([CustomerFixtures::FOREIGN_CUSTOMER_ID]))->toBe([])
            ->and($this->customers->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });
});

describe('describing one customer', function () {
    it('describes a customer the business has', function () {
        $this->customers->store(CustomerFixtures::customer());
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::mexican());

        $snapshot = $this->directory->describe(FakeBusinessContext::BUSINESS_ID, CustomerFixtures::CUSTOMER_ID);

        expect($snapshot->id)->toBe(CustomerFixtures::CUSTOMER_ID)
            ->and($snapshot->name)->toBe(CustomerFixtures::NAME)
            ->and($snapshot->phone?->nationalNumber)->toBe(PhoneNumbers::MX_NATIONAL_NUMBER);
    });

    it('goes through the batched path rather than a lookup of its own', function () {
        $this->customers->store(CustomerFixtures::customer());

        $this->directory->describe(FakeBusinessContext::BUSINESS_ID, CustomerFixtures::CUSTOMER_ID);

        expect($this->journal->entries)->toBe([
            'customers.findManyIncludingArchived',
            'phones.forCustomers',
        ]);
    });

    it('refuses a customer the business does not have', function () {
        expect(fn () => $this->directory->describe(FakeBusinessContext::BUSINESS_ID, CustomerFixtures::CUSTOMER_ID))
            ->toThrow(AppointmentCustomerNotFound::class);
    });

    it('refuses a customer of another business', function () {
        $this->customers->store(CustomerFixtures::customer(businessId: CustomerFixtures::OTHER_BUSINESS_ID));

        expect(fn () => $this->directory->describe(FakeBusinessContext::BUSINESS_ID, CustomerFixtures::CUSTOMER_ID))
            ->toThrow(AppointmentCustomerNotFound::class);
    });

    it('refuses with its own failure and names no neighbour of the customers domain', function () {
        $failure = null;

        try {
            $this->directory->describe(FakeBusinessContext::BUSINESS_ID, CustomerFixtures::CUSTOMER_ID);
        } catch (AppointmentCustomerNotFound $refused) {
            $failure = $refused;
        }

        expect($failure?->errorCode())->toBe('appointment_customer_not_found')
            ->and($failure?->kind())->toBe(DomainFailureKind::NotFound)
            ->and($failure?->getPrevious())->toBeNull();
    });
});

describe('finding or creating a guest', function () {
    beforeEach(function () {
        $this->guestAddress = new GuestAddress(
            street: CustomerFixtures::STREET,
            city: CustomerFixtures::CITY,
            stateName: CustomerFixtures::STATE_NAME,
            postalCode: CustomerFixtures::POSTAL_CODE,
            countryCode: CustomerFixtures::COUNTRY_CODE,
        );

        $this->findOrCreate = fn (GuestContact $guest): CustomerSnapshot => $this->directory->findOrCreateGuest(
            FakeBusinessContext::BUSINESS_ID,
            $guest,
        );
    });

    it('enrols a guest who left nothing but a name', function () {
        $snapshot = ($this->findOrCreate)(new GuestContact(name: CustomerFixtures::NAME, email: null, phone: null));

        expect($snapshot->id)->toBe(CustomerFixtures::GENERATED_CUSTOMER_ID)
            ->and($snapshot->name)->toBe(CustomerFixtures::NAME)
            ->and($snapshot->email)->toBeNull()
            ->and($snapshot->phone)->toBeNull()
            ->and($this->customers->saved[0]->businessId)->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('hands the typed address across to the customer it enrols, field by field', function () {
        ($this->findOrCreate)(new GuestContact(
            name: CustomerFixtures::NAME,
            email: null,
            phone: null,
            address: $this->guestAddress,
        ));

        $filed = $this->addresses->replacements[0]['address'] ?? null;

        expect($this->addresses->replacements)->toHaveCount(1)
            ->and($this->addresses->replacements[0]['customerId'])->toBe(CustomerFixtures::GENERATED_CUSTOMER_ID)
            ->and($filed?->street)->toBe(CustomerFixtures::STREET)
            ->and($filed?->city)->toBe(CustomerFixtures::CITY)
            ->and($filed?->stateId)->toBeNull()
            ->and($filed?->stateName)->toBe(CustomerFixtures::STATE_NAME)
            ->and($filed?->postalCode)->toBe(CustomerFixtures::POSTAL_CODE)
            ->and($filed?->countryCode)->toBe(CustomerFixtures::COUNTRY_CODE);
    });

    it('files no address when the guest typed none', function () {
        ($this->findOrCreate)(new GuestContact(name: CustomerFixtures::NAME, email: CustomerFixtures::EMAIL, phone: null));

        expect($this->addresses->calls)->toBe([]);
    });

    it('hands the phone across as the pair the customers domain parses', function () {
        $snapshot = ($this->findOrCreate)(new GuestContact(
            name: CustomerFixtures::NAME,
            email: null,
            phone: new GuestPhone('MX', PhoneNumbers::MX_NATIONAL_NUMBER),
        ));

        expect($snapshot->phone?->countryCode)->toBe('MX')
            ->and($snapshot->phone?->nationalNumber)->toBe(PhoneNumbers::MX_NATIONAL_NUMBER);
    });

    it('translates an address the customers domain refused into its own failure', function () {
        $failure = null;

        try {
            ($this->findOrCreate)(new GuestContact(
                name: CustomerFixtures::NAME,
                email: null,
                phone: null,
                address: new GuestAddress(street: '   ', city: null, stateName: null, postalCode: null, countryCode: 'MX'),
            ));
        } catch (InvalidGuestAddress $refused) {
            $failure = $refused;
        }

        expect($failure)->toBeInstanceOf(InvalidGuestAddress::class)
            ->and($failure?->errorCode())->toBe('invalid_guest_address')
            ->and($failure?->kind())->toBe(DomainFailureKind::Invalid)
            ->and($failure?->getPrevious())->toBeInstanceOf(InvalidCustomerAddress::class)
            ->and($this->customers->saved)->toBe([]);
    });

    it('translates a name the customers domain refused into its own failure', function () {
        expect(fn () => ($this->findOrCreate)(new GuestContact(name: '   ', email: null, phone: null)))
            ->toThrow(InvalidGuestName::class);
    });
});
