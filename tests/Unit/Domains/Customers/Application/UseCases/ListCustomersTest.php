<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\CustomerData;
use App\Domains\Customers\Application\Dtos\ListCustomersInput;
use App\Domains\Customers\Application\Presenters\CustomerPresenter;
use App\Domains\Customers\Application\UseCases\ListCustomers;
use App\Domains\Customers\ValueObjects\CustomerSort;
use App\Shared\ValueObjects\DomainFailureKind;
use App\Shared\ValueObjects\Paginated;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SortDirection;
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

    $this->build = fn (?FakeBusinessContext $business = null): ListCustomers => new ListCustomers(
        $this->customers,
        $this->phones,
        new CustomerPresenter($this->phones, $this->addresses, $this->photos),
        $business ?? new FakeBusinessContext,
    );

    $this->useCase = ($this->build)();

    $this->list = fn (array $payload = []) => $this->useCase->handle(ListCustomersInput::fromRequest($payload));
});

describe('the page it answers with', function () {
    it('answers with the page of customers the repository found', function () {
        $this->customers->returning(Paginated::of(
            [
                CustomerFixtures::customer(),
                CustomerFixtures::customer(id: CustomerFixtures::SECOND_CUSTOMER_ID, name: 'Grace Hopper'),
            ],
            42,
            Pagination::of(2, 25),
        ));

        $page = ($this->list)(['page' => 2, 'per_page' => 25])->value();

        expect($page)->toBeInstanceOf(Paginated::class)
            ->and($page->total)->toBe(42)
            ->and($page->pagination->page)->toBe(2)
            ->and($page->pagination->perPage)->toBe(25)
            ->and($page->items)->toHaveCount(2)
            ->and($page->items[0])->toBeInstanceOf(CustomerData::class)
            ->and($page->items[0]->id)->toBe(CustomerFixtures::CUSTOMER_ID)
            ->and($page->items[0]->name)->toBe(CustomerFixtures::NAME)
            ->and($page->items[1]->name)->toBe('Grace Hopper');
    });

    it('carries the phone of each row', function () {
        $this->customers->returning(Paginated::of([CustomerFixtures::customer()], 1, Pagination::of(1, 20)));
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::mexican());

        expect(($this->list)()->value()->items[0]->phone?->e164())->toBe(PhoneNumbers::MX_E164);
    });

    it('asks for the phones of the whole page in one call, never one per row', function () {
        $this->customers->returning(Paginated::of(
            [
                CustomerFixtures::customer(),
                CustomerFixtures::customer(id: CustomerFixtures::SECOND_CUSTOMER_ID),
                CustomerFixtures::customer(id: CustomerFixtures::THIRD_CUSTOMER_ID),
            ],
            3,
            Pagination::of(1, 20),
        ));

        ($this->list)();

        expect($this->phones->batchReads)->toHaveCount(1)
            ->and($this->phones->reads)->toBe([]);
    });

    it('answers an empty page with a success, not a refusal', function () {
        $page = ($this->list)()->value();

        expect($page->items)->toBe([])
            ->and($page->total)->toBe(0)
            ->and($page->lastPage())->toBe(1);
    });
});

describe('the query it builds', function () {
    it('hands the repository the query the input built', function () {
        ($this->list)([
            'search' => '  ada  ',
            'sort' => 'created_at',
            'direction' => 'desc',
            'page' => 3,
            'per_page' => 10,
        ]);

        $query = $this->customers->queries[0];

        expect($query->search?->raw())->toBe('ada')
            ->and($query->search?->tokens())->toBe(['ada'])
            ->and($query->sort)->toBe(CustomerSort::CreatedAt)
            ->and($query->direction)->toBe(SortDirection::Descending)
            ->and($query->pagination->page)->toBe(3)
            ->and($query->pagination->perPage)->toBe(10);
    });

    it('resolves a sort and a page it does not serve instead of refusing them', function () {
        $response = ($this->list)(['sort' => 'whatever', 'direction' => 'sideways', 'page' => 0, 'per_page' => 9999]);

        expect($response->succeeded())->toBeTrue()
            ->and($this->customers->queries[0]->sort)->toBe(CustomerSort::Name)
            ->and($this->customers->queries[0]->direction)->toBe(SortDirection::Ascending)
            ->and($this->customers->queries[0]->pagination->page)->toBe(1)
            ->and($this->customers->queries[0]->pagination->perPage)->toBe(Pagination::MAXIMUM_PER_PAGE);
    });
});

describe('searching by phone', function () {
    it('looks the typed term up as a phone once and carries the answer into the query', function () {
        $this->phones->store(CustomerFixtures::SECOND_CUSTOMER_ID, PhoneNumbers::mexican());

        ($this->list)(['search' => '5512']);

        expect($this->phones->fragmentLookups)->toBe(['5512'])
            ->and($this->customers->queries[0]->phoneMatches)->toBe([CustomerFixtures::SECOND_CUSTOMER_ID]);
    });

    it('asks no phone at all when nothing was typed', function () {
        ($this->list)();

        expect($this->phones->fragmentLookups)->toBe([])
            ->and($this->customers->queries[0]->phoneMatches)->toBe([]);
    });

    it('matches no phone for a term that carries no digits', function () {
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::mexican());

        ($this->list)(['search' => 'ada']);

        expect($this->customers->queries[0]->phoneMatches)->toBe([]);
    });

    it('carries the matches of every business through untouched, because the repository is what scopes them', function () {
        $this->phones->store(CustomerFixtures::FOREIGN_CUSTOMER_ID, PhoneNumbers::mexican());

        ($this->list)(['search' => '5512345678']);

        expect($this->customers->queries[0]->phoneMatches)->toBe([CustomerFixtures::FOREIGN_CUSTOMER_ID])
            ->and($this->customers->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });
});

describe('the business it reads', function () {
    beforeEach(function () {
        $this->onePage = fn () => $this->customers->returning(
            Paginated::of([CustomerFixtures::customer()], 1, Pagination::of(1, 20)),
        );
    });

    it('searches the business in context and never one a caller could name', function () {
        ($this->list)(['search' => 'ada']);

        expect($this->customers->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('asks for the photos of the page under the business in context', function () {
        ($this->onePage)();

        ($this->list)();

        expect($this->photos->batchReads)->toHaveCount(1)
            ->and($this->photos->batchReads[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->photos->batchReads[0]['customerIds'])->toBe([CustomerFixtures::CUSTOMER_ID]);
    });

    it('shows no photo that was filed under another business', function () {
        ($this->onePage)();
        $this->photos->store(CustomerFixtures::OTHER_BUSINESS_ID, CustomerFixtures::CUSTOMER_ID, CustomerFixtures::PHOTO_URL);

        expect(($this->list)()->value()->items[0]->photoUrl)->toBeNull();
    });

    it('shows the photo filed under the business in context', function () {
        ($this->onePage)();
        $this->photos->store(FakeBusinessContext::BUSINESS_ID, CustomerFixtures::CUSTOMER_ID, CustomerFixtures::PHOTO_URL);

        expect(($this->list)()->value()->items[0]->photoUrl)->toBe(CustomerFixtures::PHOTO_URL);
    });

    it('sees nothing of another business when the context names that other business', function () {
        $useCase = ($this->build)(new FakeBusinessContext(CustomerFixtures::OTHER_BUSINESS_ID));

        $useCase->handle(ListCustomersInput::fromRequest([]));

        expect($this->customers->businessIdsSeen)->toBe([CustomerFixtures::OTHER_BUSINESS_ID]);
    });
});

it('refuses a search term longer than it serves, without searching anything', function () {
    $response = ($this->list)(['search' => str_repeat('a', 121)]);

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('invalid_customer_search')
        ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
        ->and($this->customers->queries)->toBe([])
        ->and($this->customers->businessIdsSeen)->toBe([])
        ->and($this->phones->fragmentLookups)->toBe([]);
});
