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
use Tests\Support\Customers\FakeBusinessTimezone;
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
    $this->timezones = new FakeBusinessTimezone;

    $this->build = fn (?FakeBusinessContext $business = null): ListCustomers => new ListCustomers(
        $this->customers,
        $this->phones,
        new CustomerPresenter($this->phones, $this->addresses, $this->photos),
        $business ?? new FakeBusinessContext,
        $this->timezones,
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

    it('resolves a sort and a page size it does not serve instead of refusing them', function () {
        $response = ($this->list)(['sort' => 'whatever', 'direction' => 'sideways', 'per_page' => 9999]);

        expect($response->succeeded())->toBeTrue()
            ->and($this->customers->queries[0]->sort)->toBe(CustomerSort::Name)
            ->and($this->customers->queries[0]->direction)->toBe(SortDirection::Ascending)
            ->and($this->customers->queries[0]->pagination->page)->toBe(1)
            ->and($this->customers->queries[0]->pagination->perPage)->toBe(Pagination::MAXIMUM_PER_PAGE);
    });

    it('refuses a page that cannot exist without querying anything', function (int $page) {
        $response = ($this->list)(['page' => $page]);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('page_out_of_range')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->customers->queries)->toBe([]);
    })->with([
        'the zeroth page' => [0],
        'one past the deepest page' => [Pagination::MAXIMUM_PAGE + 1],
    ]);
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

    it('searches the phones of the business in context and of no other', function () {
        ($this->list)(['search' => '5512']);

        expect($this->phones->lookupBusinessIds)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('never carries a customer of another business holding a matching number into the query', function () {
        $this->phones->store(CustomerFixtures::SECOND_CUSTOMER_ID, PhoneNumbers::mexican());
        $this->phones->store(CustomerFixtures::FOREIGN_CUSTOMER_ID, PhoneNumbers::mexican(), CustomerFixtures::OTHER_BUSINESS_ID);

        ($this->list)(['search' => '5512345678']);

        expect($this->customers->queries[0]->phoneMatches)->toBe([CustomerFixtures::SECOND_CUSTOMER_ID])
            ->and($this->customers->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('searches the phones of whichever business the context names', function () {
        $this->phones->store(CustomerFixtures::SECOND_CUSTOMER_ID, PhoneNumbers::mexican());
        $this->phones->store(CustomerFixtures::FOREIGN_CUSTOMER_ID, PhoneNumbers::mexican(), CustomerFixtures::OTHER_BUSINESS_ID);

        ($this->build)(new FakeBusinessContext(CustomerFixtures::OTHER_BUSINESS_ID))
            ->handle(ListCustomersInput::fromRequest(['search' => '5512']));

        expect($this->phones->lookupBusinessIds)->toBe([CustomerFixtures::OTHER_BUSINESS_ID])
            ->and($this->customers->queries[0]->phoneMatches)->toBe([CustomerFixtures::FOREIGN_CUSTOMER_ID]);
    });

    it('matches no phone for a term carrying fewer than four digits', function (string $search) {
        $this->phones->store(CustomerFixtures::CUSTOMER_ID, PhoneNumbers::mexican());

        ($this->list)(['search' => $search]);

        expect($this->customers->queries[0]->phoneMatches)->toBe([]);
    })->with([
        'three digits' => '551',
        'three digits among letters' => 'ada 55 1',
    ]);
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

describe('filtering by registration date', function () {
    it('asks no timezone and filters on no window when no period was given', function () {
        ($this->list)(['search' => 'ada']);

        expect($this->timezones->lookups)->toBe([])
            ->and($this->customers->queries[0]->registeredWithin)->toBeNull();
    });

    it('asks no timezone when both dates arrive blank', function () {
        ($this->list)(['created_from' => '', 'created_to' => '   ']);

        expect($this->timezones->lookups)->toBe([])
            ->and($this->customers->queries[0]->registeredWithin)->toBeNull();
    });

    it('hands the repository the window the period covers in the business timezone', function () {
        ($this->list)(['created_from' => '2026-03-29', 'created_to' => '2026-03-29']);

        $window = $this->customers->queries[0]->registeredWithin;

        expect($window?->startsAt->format(DATE_ATOM))->toBe('2026-03-28T23:00:00+00:00')
            ->and($window?->endsAt->format(DATE_ATOM))->toBe('2026-03-29T22:00:00+00:00');
    });

    it('covers the twenty-five hours of a fall back day', function () {
        ($this->list)(['created_from' => '2026-10-25', 'created_to' => '2026-10-25']);

        $window = $this->customers->queries[0]->registeredWithin;

        expect($window?->startsAt->format(DATE_ATOM))->toBe('2026-10-24T22:00:00+00:00')
            ->and($window?->endsAt->format(DATE_ATOM))->toBe('2026-10-25T23:00:00+00:00');
    });

    it('reads the dates in the timezone of the business rather than in UTC', function () {
        $this->timezones = new FakeBusinessTimezone('America/Mexico_City');

        ($this->build)()->handle(ListCustomersInput::fromRequest([
            'created_from' => '2026-03-01',
            'created_to' => '2026-03-31',
        ]));

        $window = $this->customers->queries[0]->registeredWithin;

        expect($window?->startsAt->format(DATE_ATOM))->toBe('2026-03-01T06:00:00+00:00')
            ->and($window?->endsAt->format(DATE_ATOM))->toBe('2026-04-01T06:00:00+00:00');
    });

    it('asks the timezone of the business in context, once', function () {
        ($this->list)(['created_from' => '2026-03-01', 'created_to' => '2026-03-31']);

        expect($this->timezones->lookups)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('never asks the timezone of a business other than the one in context', function () {
        ($this->build)(new FakeBusinessContext(CustomerFixtures::OTHER_BUSINESS_ID))->handle(ListCustomersInput::fromRequest([
            'created_from' => '2026-03-01',
            'created_to' => '2026-03-31',
        ]));

        expect($this->timezones->lookups)->toBe([CustomerFixtures::OTHER_BUSINESS_ID])
            ->and($this->customers->businessIdsSeen)->toBe([CustomerFixtures::OTHER_BUSINESS_ID]);
    });

    it('carries the window alongside the search and its phone matches', function () {
        $this->phones->store(CustomerFixtures::SECOND_CUSTOMER_ID, PhoneNumbers::mexican());

        ($this->list)(['search' => '5512', 'created_from' => '2026-03-01', 'created_to' => '2026-03-31']);

        $query = $this->customers->queries[0];

        expect($query->search?->raw())->toBe('5512')
            ->and($query->phoneMatches)->toBe([CustomerFixtures::SECOND_CUSTOMER_ID])
            ->and($query->registeredWithin?->startsAt->format(DATE_ATOM))->toBe('2026-02-28T23:00:00+00:00')
            ->and($query->registeredWithin?->endsAt->format(DATE_ATOM))->toBe('2026-03-31T22:00:00+00:00');
    });

    it('refuses a period it cannot use, without searching or asking anything', function (array $period) {
        $response = ($this->list)(['search' => '5512', ...$period]);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_customer_registration_period')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->customers->queries)->toBe([])
            ->and($this->customers->businessIdsSeen)->toBe([])
            ->and($this->timezones->lookups)->toBe([])
            ->and($this->phones->fragmentLookups)->toBe([]);
    })->with([
        'a day the calendar does not have' => [['created_from' => '2026-02-30', 'created_to' => '2026-03-31']],
        'a date without padding' => [['created_from' => '2026-3-1', 'created_to' => '2026-03-31']],
        'only a from date' => [['created_from' => '2026-03-01']],
        'only a to date' => [['created_to' => '2026-03-31']],
        'a reversed period' => [['created_from' => '2026-03-31', 'created_to' => '2026-03-01']],
    ]);
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
