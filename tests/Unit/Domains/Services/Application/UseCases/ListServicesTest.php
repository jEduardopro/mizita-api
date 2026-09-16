<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\ListServicesInput;
use App\Domains\Services\Application\Dtos\ServiceData;
use App\Domains\Services\Application\Presenters\ServicePresenter;
use App\Domains\Services\Application\UseCases\ListServices;
use App\Domains\Services\Services\BookingLinks;
use App\Domains\Services\ValueObjects\ServiceSort;
use App\Shared\ValueObjects\DomainFailureKind;
use App\Shared\ValueObjects\Paginated;
use App\Shared\ValueObjects\Pagination;
use App\Shared\ValueObjects\SortDirection;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Services\FakeBusinessProfile;
use Tests\Support\Services\FakeServiceImages;
use Tests\Support\Services\FakeServiceRepository;
use Tests\Support\Services\FakeStaffDirectory;
use Tests\Support\Services\ServiceFixtures;

beforeEach(function () {
    $this->services = new FakeServiceRepository;
    $this->staff = FakeStaffDirectory::of(FakeBusinessContext::BUSINESS_ID, [
        ServiceFixtures::STAFF_ID => 'Ada Lovelace',
    ]);
    $this->images = new FakeServiceImages;
    $this->businesses = new FakeBusinessProfile;

    $this->useCase = new ListServices(
        $this->services,
        new ServicePresenter(
            $this->staff,
            $this->images,
            $this->businesses,
            new BookingLinks(ServiceFixtures::BASE_URL),
        ),
        new FakeBusinessContext,
    );

    $this->list = fn (array $payload = []) => $this->useCase->handle(ListServicesInput::fromRequest($payload));
});

it('answers with the page of services the repository found', function () {
    $this->services->returning(Paginated::of(
        [ServiceFixtures::service(), ServiceFixtures::service(id: ServiceFixtures::SECOND_SERVICE_ID, name: 'Barba', slug: 'barba')],
        42,
        Pagination::of(2, 25),
    ));

    $page = ($this->list)(['page' => 2, 'per_page' => 25])->value();

    expect($page)->toBeInstanceOf(Paginated::class)
        ->and($page->total)->toBe(42)
        ->and($page->pagination->page)->toBe(2)
        ->and($page->pagination->perPage)->toBe(25)
        ->and($page->items)->toHaveCount(2)
        ->and($page->items[0])->toBeInstanceOf(ServiceData::class)
        ->and($page->items[0]->id)->toBe(ServiceFixtures::SERVICE_ID)
        ->and($page->items[0]->name)->toBe(ServiceFixtures::NAME)
        ->and($page->items[1]->name)->toBe('Barba');
});

it('searches the business the context names and never one a caller could choose', function () {
    ($this->list)(['search' => 'corte']);

    expect($this->services->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID])
        ->and($this->staff->lastCall()['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
        ->and($this->businesses->calls)->toBe([FakeBusinessContext::BUSINESS_ID]);
});

it('hands the repository the query the input built', function () {
    ($this->list)(['search' => '  corte  ', 'sort' => 'price', 'direction' => 'desc', 'page' => 3, 'per_page' => 10]);

    $query = $this->services->queries[0];

    expect($query->search->raw())->toBe('corte')
        ->and($query->search->tokens())->toBe(['corte'])
        ->and($query->sort)->toBe(ServiceSort::Price)
        ->and($query->direction)->toBe(SortDirection::Descending)
        ->and($query->pagination->page)->toBe(3)
        ->and($query->pagination->perPage)->toBe(10);
});

it('resolves a sort and a page it does not serve instead of refusing them', function () {
    $response = ($this->list)(['sort' => 'whatever', 'direction' => 'sideways', 'page' => 0, 'per_page' => 9999]);

    expect($response->succeeded())->toBeTrue()
        ->and($this->services->queries[0]->sort)->toBe(ServiceSort::Name)
        ->and($this->services->queries[0]->direction)->toBe(SortDirection::Ascending)
        ->and($this->services->queries[0]->pagination->page)->toBe(1)
        ->and($this->services->queries[0]->pagination->perPage)->toBe(Pagination::MAXIMUM_PER_PAGE);
});

it('answers an empty page with a success, not a refusal', function () {
    $page = ($this->list)()->value();

    expect($page->items)->toBe([])
        ->and($page->total)->toBe(0)
        ->and($page->lastPage())->toBe(1);
});

it('refuses a search term longer than it serves without searching', function () {
    $response = ($this->list)(['search' => str_repeat('a', 121)]);

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('invalid_service_search')
        ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
        ->and($this->services->queries)->toBe([])
        ->and($this->services->businessIdsSeen)->toBe([]);
});
