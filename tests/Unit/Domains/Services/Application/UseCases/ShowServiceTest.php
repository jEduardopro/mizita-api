<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\ServiceData;
use App\Domains\Services\Application\Dtos\ShowServiceInput;
use App\Domains\Services\Application\Presenters\ServicePresenter;
use App\Domains\Services\Application\UseCases\ShowService;
use App\Domains\Services\Services\BookingLinks;
use App\Shared\ValueObjects\DomainFailureKind;
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
    $this->images = new FakeServiceImages([
        ServiceFixtures::SERVICE_ID => 'https://cdn.mizita.test/corte.png',
    ]);

    $this->useCase = new ShowService(
        $this->services,
        new ServicePresenter(
            $this->staff,
            $this->images,
            new FakeBusinessProfile,
            new BookingLinks(ServiceFixtures::BASE_URL),
        ),
        new FakeBusinessContext,
    );

    $this->show = fn (string $serviceId = ServiceFixtures::SERVICE_ID) => $this->useCase
        ->handle(new ShowServiceInput($serviceId));
});

it('answers with the service of the business in context', function () {
    $this->services->store(ServiceFixtures::service());

    $data = ($this->show)()->value();

    expect($data)->toBeInstanceOf(ServiceData::class)
        ->and($data->id)->toBe(ServiceFixtures::SERVICE_ID)
        ->and($data->name)->toBe(ServiceFixtures::NAME)
        ->and($data->slug)->toBe(ServiceFixtures::SLUG)
        ->and($data->price)->toBe('250.00')
        ->and($data->imageUrl)->toBe('https://cdn.mizita.test/corte.png')
        ->and($data->bookingUrl)->toBe('https://mizita.test/ada-salon/corte-de-pelo')
        ->and($data->staff)->toHaveCount(1)
        ->and($data->staff[0]->name)->toBe('Ada Lovelace');
});

it('looks the service up under the business the context names', function () {
    $this->services->store(ServiceFixtures::service());

    ($this->show)();

    expect($this->services->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
});

it('does not find a service that belongs to another business', function () {
    $this->services->store(ServiceFixtures::service(businessId: ServiceFixtures::OTHER_BUSINESS_ID));

    $response = ($this->show)();

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('service_not_found')
        ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
});

it('answers not found for a service nobody has', function () {
    $response = ($this->show)(ServiceFixtures::SECOND_SERVICE_ID);

    expect($response->error()->code)->toBe('service_not_found');
});

it('answers not found for an identifier that cannot be a service, without asking the repository', function () {
    $response = ($this->show)('not-a-uuid');

    expect($response->error()->code)->toBe('service_not_found')
        ->and($this->services->businessIdsSeen)->toBe([]);
});
