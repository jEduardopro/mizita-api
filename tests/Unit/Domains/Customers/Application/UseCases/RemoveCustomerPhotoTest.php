<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\CustomerData;
use App\Domains\Customers\Application\Dtos\RemoveCustomerPhotoInput;
use App\Domains\Customers\Application\Presenters\CustomerPresenter;
use App\Domains\Customers\Application\UseCases\RemoveCustomerPhoto;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Customers\CustomerFixtures;
use Tests\Support\Customers\FakeCustomerAddressBook;
use Tests\Support\Customers\FakeCustomerPhoneBook;
use Tests\Support\Customers\FakeCustomerPhotos;
use Tests\Support\Customers\FakeCustomerRepository;
use Tests\Support\FakeBusinessContext;
use Tests\Support\PhoneNumbers;

beforeEach(function () {
    $this->customers = new FakeCustomerRepository;
    $this->phones = new FakeCustomerPhoneBook;
    $this->addresses = new FakeCustomerAddressBook;
    $this->photos = new FakeCustomerPhotos;

    $this->build = fn (?FakeBusinessContext $business = null): RemoveCustomerPhoto => new RemoveCustomerPhoto(
        $this->customers,
        $this->photos,
        new CustomerPresenter($this->phones, $this->addresses, $this->photos),
        $business ?? new FakeBusinessContext,
    );

    $this->useCase = ($this->build)();

    $this->remove = fn (string $customerId = CustomerFixtures::CUSTOMER_ID) => $this->useCase
        ->handle(new RemoveCustomerPhotoInput($customerId));
});

describe('removing a photo', function () {
    beforeEach(function () {
        $this->customers->store(CustomerFixtures::customer());
        $this->photos->store(FakeBusinessContext::BUSINESS_ID, CustomerFixtures::CUSTOMER_ID, CustomerFixtures::PHOTO_URL);
    });

    it('clears the photo and answers with the customer without one', function () {
        $data = ($this->remove)()->value();

        expect($this->photos->removals)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'customerId' => CustomerFixtures::CUSTOMER_ID,
        ]])
            ->and($data)->toBeInstanceOf(CustomerData::class)
            ->and($data->id)->toBe(CustomerFixtures::CUSTOMER_ID)
            ->and($data->photoUrl)->toBeNull();
    });

    it('answers with the whole customer, everything but the photo untouched', function () {
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::mexican());
        $this->addresses->store(CustomerFixtures::CUSTOMER_ID, CustomerFixtures::address());

        $data = ($this->remove)()->value();

        expect($data->name)->toBe(CustomerFixtures::NAME)
            ->and($data->email)->toBe(CustomerFixtures::EMAIL)
            ->and($data->phone?->e164())->toBe(PhoneNumbers::MX_E164)
            ->and($data->birthDate)->toEqual(CustomerFixtures::birthDate())
            ->and($data->notes)->toBe(CustomerFixtures::NOTES)
            ->and($data->address?->street)->toBe(CustomerFixtures::STREET)
            ->and($data->createdAt)->toEqual(CustomerFixtures::now());
    });

    it('succeeds again when asked a second time', function () {
        ($this->remove)();

        $response = ($this->remove)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value()->photoUrl)->toBeNull()
            ->and($this->photos->removals)->toHaveCount(2);
    });

    it('looks the customer up under the business in context, never one a caller could name', function () {
        ($this->remove)();

        expect($this->customers->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('clears the photo under the business in context, never under one the port has to guess', function () {
        ($this->remove)();

        expect($this->photos->removals[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID);
    });
});

it('succeeds for a customer that never had a photo', function () {
    $this->customers->store(CustomerFixtures::customer());
    $this->photos->knows(FakeBusinessContext::BUSINESS_ID, CustomerFixtures::CUSTOMER_ID);

    $response = ($this->remove)();

    expect($response->succeeded())->toBeTrue()
        ->and($response->value()->photoUrl)->toBeNull()
        ->and($this->photos->removals)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'customerId' => CustomerFixtures::CUSTOMER_ID,
        ]]);
});

describe('refusing to remove', function () {
    it('does not touch the photo of a customer that belongs to another business', function () {
        $this->customers->store(CustomerFixtures::customer(businessId: CustomerFixtures::OTHER_BUSINESS_ID));
        $this->photos->store(CustomerFixtures::OTHER_BUSINESS_ID, CustomerFixtures::CUSTOMER_ID, CustomerFixtures::PHOTO_URL);

        $response = ($this->remove)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('customer_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->photos->removals)->toBe([]);
    });

    it('answers not found, never forbidden, to a caller reaching into another business', function () {
        $this->customers->store(CustomerFixtures::customer());
        $this->photos->store(FakeBusinessContext::BUSINESS_ID, CustomerFixtures::CUSTOMER_ID, CustomerFixtures::PHOTO_URL);

        $response = ($this->build)(new FakeBusinessContext(CustomerFixtures::OTHER_BUSINESS_ID))
            ->handle(new RemoveCustomerPhotoInput(CustomerFixtures::CUSTOMER_ID));

        expect($response->error()->code)->toBe('customer_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->photos->removals)->toBe([])
            ->and($this->customers->businessIdsSeen)->toBe([CustomerFixtures::OTHER_BUSINESS_ID]);
    });

    it('answers not found for a customer nobody has, having removed nothing', function () {
        $response = ($this->remove)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('customer_not_found')
            ->and($this->photos->removals)->toBe([]);
    });

    it('answers not found for an identifier that cannot be a customer, without asking the repository', function (string $customerId) {
        $response = ($this->remove)($customerId);

        expect($response->error()->code)->toBe('customer_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->customers->businessIdsSeen)->toBe([])
            ->and($this->photos->removals)->toBe([]);
    })->with([
        'plain text' => 'not-a-uuid',
        'empty' => '',
        'a number' => '42',
        'a truncated uuid' => '01930000-0000-7000-8000-0000000000c',
    ]);
});
