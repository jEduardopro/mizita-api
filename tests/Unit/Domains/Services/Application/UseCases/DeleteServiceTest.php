<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\DeleteServiceInput;
use App\Domains\Services\Application\UseCases\DeleteService;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Services\FakeServiceRepository;
use Tests\Support\Services\ServiceFixtures;

beforeEach(function () {
    $this->services = new FakeServiceRepository;
    $this->useCase = new DeleteService($this->services, new FakeBusinessContext);

    $this->delete = fn (string $serviceId = ServiceFixtures::SERVICE_ID) => $this->useCase
        ->handle(new DeleteServiceInput($serviceId));
});

it('deletes the service and answers with nothing to show', function () {
    $this->services->store(ServiceFixtures::service());

    $response = ($this->delete)();

    expect($response->succeeded())->toBeTrue()
        ->and($response->value())->toBeNull()
        ->and($this->services->deleted)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'id' => ServiceFixtures::SERVICE_ID,
        ]]);
});

it('deletes under the business in context, never one a caller could name', function () {
    $this->services->store(ServiceFixtures::service());

    ($this->delete)();

    expect($this->services->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
});

it('does not delete a service that belongs to another business', function () {
    $this->services->store(ServiceFixtures::service(businessId: ServiceFixtures::OTHER_BUSINESS_ID));

    $response = ($this->delete)();

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('service_not_found')
        ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
        ->and($this->services->deleted)->toBe([]);
});

it('answers not found for a service nobody has', function () {
    expect(($this->delete)()->error()->code)->toBe('service_not_found');
});

it('answers not found for an identifier that cannot be a service, without asking the repository', function () {
    $response = ($this->delete)('not-a-uuid');

    expect($response->error()->code)->toBe('service_not_found')
        ->and($this->services->businessIdsSeen)->toBe([]);
});
