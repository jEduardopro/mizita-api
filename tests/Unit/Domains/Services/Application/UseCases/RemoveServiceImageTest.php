<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\RemoveServiceImageInput;
use App\Domains\Services\Application\Presenters\ServicePresenter;
use App\Domains\Services\Application\UseCases\RemoveServiceImage;
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
    $this->images = FakeServiceImages::of(FakeBusinessContext::BUSINESS_ID, [
        ServiceFixtures::SERVICE_ID => 'https://cdn.mizita.test/corte.png',
    ]);
    $this->staff = FakeStaffDirectory::of(FakeBusinessContext::BUSINESS_ID, [
        ServiceFixtures::STAFF_ID => 'Ada Lovelace',
    ]);

    $this->useCase = new RemoveServiceImage(
        $this->services,
        $this->images,
        new ServicePresenter(
            $this->staff,
            $this->images,
            new FakeBusinessProfile,
            new BookingLinks(ServiceFixtures::BASE_URL),
        ),
        new FakeBusinessContext,
    );

    $this->remove = fn (string $serviceId = ServiceFixtures::SERVICE_ID) => $this->useCase
        ->handle(new RemoveServiceImageInput($serviceId));
});

it('removes the image and answers with the service without one', function () {
    $this->services->store(ServiceFixtures::service());

    $data = ($this->remove)()->value();

    expect($this->images->removed)->toBe([[
        'businessId' => FakeBusinessContext::BUSINESS_ID,
        'serviceId' => ServiceFixtures::SERVICE_ID,
    ]])->and($data->id)->toBe(ServiceFixtures::SERVICE_ID)
        ->and($data->imageUrl)->toBeNull();
});

it('leaves the file a neighbouring business filed under that same service id', function () {
    $this->images->add(ServiceFixtures::OTHER_BUSINESS_ID, [
        ServiceFixtures::SERVICE_ID => 'https://cdn.mizita.test/otro.png',
    ]);
    $this->services->store(ServiceFixtures::service());

    ($this->remove)();

    expect($this->images->urlFor(FakeBusinessContext::BUSINESS_ID, ServiceFixtures::SERVICE_ID))->toBeNull()
        ->and($this->images->urlFor(ServiceFixtures::OTHER_BUSINESS_ID, ServiceFixtures::SERVICE_ID))
        ->toBe('https://cdn.mizita.test/otro.png');
});

it('succeeds when the service had no image to begin with', function () {
    $this->services->store(ServiceFixtures::service(id: ServiceFixtures::SECOND_SERVICE_ID));

    $response = ($this->remove)(ServiceFixtures::SECOND_SERVICE_ID);

    expect($response->succeeded())->toBeTrue()
        ->and($response->value()->imageUrl)->toBeNull()
        ->and($this->images->removed)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'serviceId' => ServiceFixtures::SECOND_SERVICE_ID,
        ]]);
});

it('succeeds again when asked a second time', function () {
    $this->services->store(ServiceFixtures::service());

    ($this->remove)();
    $response = ($this->remove)();

    expect($response->succeeded())->toBeTrue()
        ->and($response->value()->imageUrl)->toBeNull()
        ->and($this->images->removed)->toHaveCount(2);
});

it('looks the service up under the business in context', function () {
    $this->services->store(ServiceFixtures::service());

    ($this->remove)();

    expect($this->services->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
});

it('does not touch the image of a service that belongs to another business', function () {
    $this->services->store(ServiceFixtures::service(businessId: ServiceFixtures::OTHER_BUSINESS_ID));

    $response = ($this->remove)();

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('service_not_found')
        ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
        ->and($this->images->removed)->toBe([]);
});

it('answers not found for a service nobody has', function () {
    expect(($this->remove)(ServiceFixtures::THIRD_SERVICE_ID)->error()->code)->toBe('service_not_found')
        ->and($this->images->removed)->toBe([]);
});

it('answers not found for an identifier that cannot be a service, without asking the repository', function () {
    $response = ($this->remove)('not-a-uuid');

    expect($response->error()->code)->toBe('service_not_found')
        ->and($this->services->businessIdsSeen)->toBe([])
        ->and($this->images->removed)->toBe([]);
});
