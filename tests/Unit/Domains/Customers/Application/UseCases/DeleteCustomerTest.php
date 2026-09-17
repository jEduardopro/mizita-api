<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\DeleteCustomerInput;
use App\Domains\Customers\Application\UseCases\DeleteCustomer;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Customers\CustomerFixtures;
use Tests\Support\Customers\CustomerJournal;
use Tests\Support\Customers\FakeCustomerAddressBook;
use Tests\Support\Customers\FakeCustomerPhoneBook;
use Tests\Support\Customers\FakeCustomerPhotos;
use Tests\Support\Customers\FakeCustomerRepository;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeTransactionManager;
use Tests\Support\PhoneNumbers;

beforeEach(function () {
    $this->transactions = new FakeTransactionManager;
    $this->journal = new CustomerJournal($this->transactions);
    $this->customers = new FakeCustomerRepository($this->journal);
    $this->phones = new FakeCustomerPhoneBook($this->journal);
    $this->addresses = new FakeCustomerAddressBook($this->journal);
    $this->photos = new FakeCustomerPhotos($this->journal);

    $this->build = fn (?FakeBusinessContext $business = null): DeleteCustomer => new DeleteCustomer(
        $this->customers,
        $this->phones,
        $this->addresses,
        $this->photos,
        $business ?? new FakeBusinessContext,
        $this->transactions,
    );

    $this->useCase = ($this->build)();

    $this->delete = fn (string $customerId = CustomerFixtures::CUSTOMER_ID) => $this->useCase
        ->handle(new DeleteCustomerInput($customerId));
});

describe('deleting a customer', function () {
    beforeEach(function () {
        $this->customers->store(CustomerFixtures::customer());
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::mexican());
        $this->addresses->store(CustomerFixtures::CUSTOMER_ID, CustomerFixtures::address());
        $this->photos->store(FakeBusinessContext::BUSINESS_ID, CustomerFixtures::CUSTOMER_ID, CustomerFixtures::PHOTO_URL);
    });

    it('deletes the customer and answers with nothing to show', function () {
        $response = ($this->delete)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull()
            ->and($this->customers->deleted)->toBe([[
                'businessId' => FakeBusinessContext::BUSINESS_ID,
                'id' => CustomerFixtures::CUSTOMER_ID,
            ]]);
    });

    it('takes the phone, the address and the photo with it', function () {
        ($this->delete)();

        expect($this->phones->removals)->toBe([CustomerFixtures::CUSTOMER_ID])
            ->and($this->addresses->removals)->toBe([CustomerFixtures::CUSTOMER_ID])
            ->and($this->photos->removals)->toBe([[
                'businessId' => FakeBusinessContext::BUSINESS_ID,
                'customerId' => CustomerFixtures::CUSTOMER_ID,
            ]]);
    });

    it('clears the photo under the business in context, never under one the port has to guess', function () {
        ($this->delete)();

        expect($this->photos->removals[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('lets go of the phone, the address and the photo before the customer, which is what keeps their owner resolvable', function () {
        ($this->delete)();

        expect($this->journal->entries)->toBe([
            'customers.find',
            'phones.remove',
            'addresses.remove',
            'photos.remove',
            'customers.delete',
        ]);
    });

    it('clears the photo while the customer it hangs off is still there to resolve', function () {
        ($this->delete)();

        expect(array_search('photos.remove', $this->journal->entries, true))
            ->toBeLessThan(array_search('customers.delete', $this->journal->entries, true));
    });

    it('does all of it inside one transaction', function () {
        ($this->delete)();

        expect($this->transactions->runs())->toBe(1)
            ->and($this->journal->outsideTransaction)->toBe([]);
    });

    it('deletes under the business in context, never one a caller could name', function () {
        ($this->delete)();

        expect(array_unique($this->customers->businessIdsSeen))->toBe([FakeBusinessContext::BUSINESS_ID]);
    });
});

describe('refusing to delete', function () {
    it('does not delete a customer that belongs to another business, nor anything hanging off it', function () {
        $this->customers->store(CustomerFixtures::customer());
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::mexican());

        $response = ($this->build)(new FakeBusinessContext(CustomerFixtures::OTHER_BUSINESS_ID))
            ->handle(new DeleteCustomerInput(CustomerFixtures::CUSTOMER_ID));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('customer_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->customers->deleted)->toBe([])
            ->and($this->phones->removals)->toBe([])
            ->and($this->addresses->removals)->toBe([])
            ->and($this->photos->removals)->toBe([]);
    });

    it('answers not found for a customer nobody has, having removed nothing', function () {
        $response = ($this->delete)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('customer_not_found')
            ->and($this->phones->removals)->toBe([])
            ->and($this->addresses->removals)->toBe([])
            ->and($this->photos->removals)->toBe([])
            ->and($this->customers->deleted)->toBe([]);
    });

    it('answers not found for an identifier that cannot be a customer, without opening a transaction', function (string $customerId) {
        $response = ($this->delete)($customerId);

        expect($response->error()->code)->toBe('customer_not_found')
            ->and($this->journal->entries)->toBe([])
            ->and($this->transactions->runs())->toBe(0);
    })->with([
        'plain text' => 'not-a-uuid',
        'empty' => '',
        'a uuid with a stray character' => '01930000-0000-7000-8000-0000000000c1x',
    ]);
});
