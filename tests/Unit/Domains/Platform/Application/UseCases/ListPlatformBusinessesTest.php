<?php

declare(strict_types=1);

use App\Domains\Platform\Application\Dtos\ListPlatformBusinessesInput;
use App\Domains\Platform\Application\Dtos\PlatformBusinessData;
use App\Domains\Platform\Application\UseCases\ListPlatformBusinesses;
use App\Domains\Platform\ValueObjects\Plan;
use App\Domains\Platform\ValueObjects\PlatformBusinessSort;
use App\Shared\ValueObjects\DomainFailureKind;
use App\Shared\ValueObjects\Paginated;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SortDirection;
use Tests\Support\FakeClock;
use Tests\Support\Platform\FakeBusinessPlans;
use Tests\Support\Platform\FakePlatformBusinessDirectory;
use Tests\Support\Platform\PlatformBusinessFixtures;

beforeEach(function () {
    $this->directory = new FakePlatformBusinessDirectory;
    $this->plans = new FakeBusinessPlans;
    $this->clock = new FakeClock(PlatformBusinessFixtures::now());

    $this->list = fn (array $payload = []) => (new ListPlatformBusinesses($this->directory, $this->plans, $this->clock))
        ->handle(ListPlatformBusinessesInput::fromRequest($payload));

    $this->twoBusinesses = fn (): Paginated => Paginated::of(
        [
            PlatformBusinessFixtures::business(),
            PlatformBusinessFixtures::business(
                id: PlatformBusinessFixtures::SECOND_BUSINESS_ID,
                name: 'Estética Ñandú',
                slug: 'estetica-nandu',
                createdAt: '2026-01-02T23:15:00+00:00',
                owner: null,
                servicesCount: 0,
                customersCount: 0,
            ),
        ],
        42,
        Pagination::of(3, 10),
    );
});

describe('the page it answers with', function () {
    it('answers with every business the directory found, field by field', function () {
        $this->directory->returning(($this->twoBusinesses)());
        $this->plans->granting(PlatformBusinessFixtures::BUSINESS_ID, Plan::Complete);

        $items = ($this->list)()->value()->items;

        expect($items)->toHaveCount(2)
            ->and($items[0])->toBeInstanceOf(PlatformBusinessData::class)
            ->and($items[0]->id)->toBe(PlatformBusinessFixtures::BUSINESS_ID)
            ->and($items[0]->name)->toBe(PlatformBusinessFixtures::BUSINESS_NAME)
            ->and($items[0]->slug)->toBe(PlatformBusinessFixtures::BUSINESS_SLUG)
            ->and($items[0]->createdAt)->toEqual(new DateTimeImmutable(PlatformBusinessFixtures::CREATED_AT))
            ->and($items[0]->owner?->name)->toBe(PlatformBusinessFixtures::OWNER_NAME)
            ->and($items[0]->owner?->email)->toBe(PlatformBusinessFixtures::OWNER_EMAIL)
            ->and($items[0]->servicesCount)->toBe(4)
            ->and($items[0]->customersCount)->toBe(37)
            ->and($items[0]->plan)->toBe(Plan::Complete)
            ->and($items[1]->id)->toBe(PlatformBusinessFixtures::SECOND_BUSINESS_ID)
            ->and($items[1]->name)->toBe('Estética Ñandú')
            ->and($items[1]->slug)->toBe('estetica-nandu')
            ->and($items[1]->createdAt)->toEqual(new DateTimeImmutable('2026-01-02T23:15:00+00:00'))
            ->and($items[1]->servicesCount)->toBe(0)
            ->and($items[1]->customersCount)->toBe(0)
            ->and($items[1]->plan)->toBe(Plan::Free);
    });

    it('carries a business with no owner through with a null owner', function () {
        $this->directory->returning(($this->twoBusinesses)());

        expect(($this->list)()->value()->items[1]->owner)->toBeNull();
    });

    it('hands back every business under its uuid', function () {
        $this->directory->returning(($this->twoBusinesses)());

        $ids = array_map(fn (PlatformBusinessData $business): string => $business->id, ($this->list)()->value()->items);

        expect($ids)->each->toMatch(PlatformBusinessFixtures::UUID_PATTERN);
    });

    it('keeps the total and the pagination of the page the directory answered', function () {
        $this->directory->returning(($this->twoBusinesses)());

        $page = ($this->list)()->value();

        expect($page)->toBeInstanceOf(Paginated::class)
            ->and($page->total)->toBe(42)
            ->and($page->pagination->page)->toBe(3)
            ->and($page->pagination->perPage)->toBe(10)
            ->and($page->lastPage())->toBe(5);
    });

    it('keeps the order the directory sorted the businesses in', function () {
        $this->directory->returning(Paginated::of(
            [
                PlatformBusinessFixtures::business(id: PlatformBusinessFixtures::THIRD_BUSINESS_ID),
                PlatformBusinessFixtures::business(id: PlatformBusinessFixtures::BUSINESS_ID),
                PlatformBusinessFixtures::business(id: PlatformBusinessFixtures::SECOND_BUSINESS_ID),
            ],
            3,
            Pagination::of(1, 20),
        ));

        $ids = array_map(fn (PlatformBusinessData $business): string => $business->id, ($this->list)()->value()->items);

        expect($ids)->toBe([
            PlatformBusinessFixtures::THIRD_BUSINESS_ID,
            PlatformBusinessFixtures::BUSINESS_ID,
            PlatformBusinessFixtures::SECOND_BUSINESS_ID,
        ]);
    });

    it('answers an empty page with a success, not a refusal', function () {
        $response = ($this->list)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value()->items)->toBe([])
            ->and($response->value()->total)->toBe(0)
            ->and($response->value()->lastPage())->toBe(1);
    });
});

describe('the plan each business is shown on', function () {
    it('shows each business on the plan granted to that very business', function () {
        $this->directory->returning(Paginated::of(
            [
                PlatformBusinessFixtures::business(id: PlatformBusinessFixtures::BUSINESS_ID),
                PlatformBusinessFixtures::business(id: PlatformBusinessFixtures::SECOND_BUSINESS_ID),
                PlatformBusinessFixtures::business(id: PlatformBusinessFixtures::THIRD_BUSINESS_ID),
            ],
            3,
            Pagination::of(1, 20),
        ));
        $this->plans
            ->granting(PlatformBusinessFixtures::BUSINESS_ID, Plan::Free)
            ->granting(PlatformBusinessFixtures::SECOND_BUSINESS_ID, Plan::Complete)
            ->granting(PlatformBusinessFixtures::THIRD_BUSINESS_ID, Plan::Free);

        $plans = array_map(fn (PlatformBusinessData $business): Plan => $business->plan, ($this->list)()->value()->items);

        expect($plans)->toBe([Plan::Free, Plan::Complete, Plan::Free]);
    });

    it('asks the plans of exactly the businesses on the page, once', function () {
        $this->directory->returning(($this->twoBusinesses)());

        ($this->list)();

        expect($this->plans->businessIdsAsked)->toBe([
            [PlatformBusinessFixtures::BUSINESS_ID, PlatformBusinessFixtures::SECOND_BUSINESS_ID],
        ]);
    });

    it('asks the plans as of the instant the clock reads', function () {
        $this->directory->returning(($this->twoBusinesses)());

        ($this->list)();

        expect($this->plans->instantsAsked)->toHaveCount(1)
            ->and($this->plans->instantsAsked[0])->toEqual(PlatformBusinessFixtures::now());
    });

    it('asks the plans as of a later instant once the clock has moved on', function () {
        $this->directory->returning(($this->twoBusinesses)());
        $this->clock->advance('PT90M');

        ($this->list)();

        expect($this->plans->instantsAsked[0])->toEqual(new DateTimeImmutable('2026-09-25T16:30:00+00:00'));
    });
});

describe('the query it hands the directory', function () {
    it('carries the search, the sort, the direction and the pagination the input asked for', function () {
        ($this->list)([
            'search' => '  Barbería   Centro ',
            'sort' => 'customers_count',
            'direction' => 'asc',
            'page' => 3,
            'per_page' => 50,
        ]);

        $query = $this->directory->queries[0];

        expect($this->directory->queries)->toHaveCount(1)
            ->and($query->search?->raw())->toBe('Barbería Centro')
            ->and($query->sort)->toBe(PlatformBusinessSort::CustomersCount)
            ->and($query->direction)->toBe(SortDirection::Ascending)
            ->and($query->pagination->page)->toBe(3)
            ->and($query->pagination->perPage)->toBe(50);
    });

    it('asks for every business newest first, on the first page of the default size, when nothing was asked for', function () {
        ($this->list)();

        $query = $this->directory->queries[0];

        expect($query->search)->toBeNull()
            ->and($query->sort)->toBe(PlatformBusinessSort::CreatedAt)
            ->and($query->direction)->toBe(SortDirection::Descending)
            ->and($query->pagination->page)->toBe(1)
            ->and($query->pagination->perPage)->toBe(Pagination::DEFAULT_PER_PAGE);
    });
});

describe('refusing', function () {
    it('refuses a filter it cannot serve, without reading the directory or any plan', function (array $payload) {
        $response = ($this->list)($payload);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_platform_business_filter')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->directory->queries)->toBe([])
            ->and($this->plans->businessIdsAsked)->toBe([]);
    })->with([
        'a search past 120 characters' => [['search' => str_repeat('a', 121)]],
        'an unknown sort' => [['sort' => 'plan']],
        'an unknown direction' => [['direction' => 'sideways']],
        'a page below the first' => [['page' => 0]],
        'a page size of zero' => [['per_page' => 0]],
        'a page size past 100' => [['per_page' => 101]],
    ]);

    it('lets a failure that is no refusal escape to the caller', function () {
        $this->directory->failingWith(new RuntimeException('connection lost'));

        expect(fn () => ($this->list)())->toThrow(RuntimeException::class, 'connection lost');
    });
});
