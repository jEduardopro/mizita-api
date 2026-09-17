<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\CustomerData;
use App\Domains\Customers\Application\Dtos\ShowCustomerInput;
use App\Domains\Customers\Application\Presenters\CustomerPresenter;
use App\Domains\Customers\Application\UseCases\ShowCustomer;
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

    $this->build = fn (?FakeBusinessContext $business = null): ShowCustomer => new ShowCustomer(
        $this->customers,
        new CustomerPresenter($this->phones, $this->addresses, $this->photos),
        $business ?? new FakeBusinessContext,
    );

    $this->useCase = ($this->build)();

    $this->show = fn (string $customerId = CustomerFixtures::CUSTOMER_ID) => $this->useCase
        ->handle(new ShowCustomerInput($customerId));
});

it('answers with the whole customer, phone and address included', function () {
    $this->customers->store(CustomerFixtures::customer());
    $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::mexican());
    $this->addresses->store(CustomerFixtures::CUSTOMER_ID, CustomerFixtures::address());

    $data = ($this->show)()->value();

    expect($data)->toBeInstanceOf(CustomerData::class)
        ->and($data->id)->toBe(CustomerFixtures::CUSTOMER_ID)
        ->and($data->name)->toBe(CustomerFixtures::NAME)
        ->and($data->email)->toBe(CustomerFixtures::EMAIL)
        ->and($data->phone?->e164())->toBe(PhoneNumbers::MX_E164)
        ->and($data->birthDate)->toEqual(CustomerFixtures::birthDate())
        ->and($data->notes)->toBe(CustomerFixtures::NOTES)
        ->and($data->address?->street)->toBe(CustomerFixtures::STREET)
        ->and($data->createdAt)->toEqual(CustomerFixtures::now());
});

it('answers with a customer that carries neither phone nor address', function () {
    $this->customers->store(CustomerFixtures::customer());

    $data = ($this->show)()->value();

    expect($data->phone)->toBeNull()
        ->and($data->address)->toBeNull();
});

it('reads under the business in context, never one a caller could name', function () {
    $this->customers->store(CustomerFixtures::customer());

    ($this->show)();

    expect($this->customers->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
});

it('does not hand back a customer that belongs to another business', function () {
    $this->customers->store(CustomerFixtures::customer(businessId: CustomerFixtures::OTHER_BUSINESS_ID));

    $response = ($this->show)();

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('customer_not_found')
        ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
});

it('hands another business nothing of this one, even by the right identifier', function () {
    $this->customers->store(CustomerFixtures::customer());

    $response = ($this->build)(new FakeBusinessContext(CustomerFixtures::OTHER_BUSINESS_ID))
        ->handle(new ShowCustomerInput(CustomerFixtures::CUSTOMER_ID));

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('customer_not_found')
        ->and($this->customers->businessIdsSeen)->toBe([CustomerFixtures::OTHER_BUSINESS_ID]);
});

it('answers not found for a customer nobody has', function () {
    expect(($this->show)()->error()->code)->toBe('customer_not_found');
});

it('answers not found for an identifier that cannot be a customer, without asking the repository', function (string $customerId) {
    $response = ($this->show)($customerId);

    expect($response->error()->code)->toBe('customer_not_found')
        ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
        ->and($this->customers->businessIdsSeen)->toBe([]);
})->with([
    'plain text' => 'not-a-uuid',
    'empty' => '',
    'a number' => '42',
    'a truncated uuid' => '01930000-0000-7000-8000-0000000000c',
]);
