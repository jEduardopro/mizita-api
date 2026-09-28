<?php

declare(strict_types=1);

use App\Domains\Availability\Exceptions\BookableServiceNotFound;
use App\Domains\Availability\Infrastructure\Gateways\ServicesBookableServices;
use App\Domains\Services\Application\Services\BookableServiceCatalog;
use App\Domains\Services\Contracts\ServiceAllowance;
use App\Domains\Services\Contracts\ServiceRepository;
use App\Domains\Services\Entities\Service;
use App\Domains\Services\Exceptions\ServiceNotFound;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Services\ServiceFixtures;

const BOOKABLE_SERVICES_FOURTH_ID = '01930000-0000-7000-8000-0000000000e4';

const BOOKABLE_SERVICES_FREE_LIMIT = 3;

beforeEach(function () {
    $this->activeIdsOldestFirst = [ServiceFixtures::SERVICE_ID];
    $this->activeServiceLimit = null;
    $this->serviceLookups = [];
    $this->catalogLookups = [];
    $this->allowanceLookups = [];
    $this->offered = ServiceFixtures::service(staffIds: [ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID]);

    $repository = Mockery::mock(ServiceRepository::class);
    $repository->shouldReceive('findForBusiness')->andReturnUsing(function (string $businessId, string $id): Service {
        $this->serviceLookups[] = ['businessId' => $businessId, 'id' => $id];

        return $this->offered ?? throw ServiceNotFound::withId($id);
    });
    $repository->shouldReceive('activeIdsOldestFirst')->andReturnUsing(function (string $businessId): array {
        $this->catalogLookups[] = $businessId;

        return $this->activeIdsOldestFirst;
    });

    $allowance = Mockery::mock(ServiceAllowance::class);
    $allowance->shouldReceive('activeServiceLimitFor')->andReturnUsing(function (string $businessId): ?int {
        $this->allowanceLookups[] = $businessId;

        return $this->activeServiceLimit;
    });

    $this->gateway = new ServicesBookableServices(
        $repository,
        new BookableServiceCatalog($repository, $allowance),
    );

    $this->describeIn = fn (string $serviceId = ServiceFixtures::SERVICE_ID) => $this->gateway->describe(
        FakeBusinessContext::BUSINESS_ID,
        $serviceId,
    );

    $this->refusalFor = function (string $serviceId = ServiceFixtures::SERVICE_ID): ?BookableServiceNotFound {
        try {
            ($this->describeIn)($serviceId);
        } catch (BookableServiceNotFound $refused) {
            return $refused;
        }

        return null;
    };

    $this->offerOnFree = function (string $serviceId): void {
        $this->offered = ServiceFixtures::service(id: $serviceId);
        $this->activeServiceLimit = BOOKABLE_SERVICES_FREE_LIMIT;
        $this->activeIdsOldestFirst = [
            ServiceFixtures::SERVICE_ID,
            ServiceFixtures::SECOND_SERVICE_ID,
            ServiceFixtures::THIRD_SERVICE_ID,
            BOOKABLE_SERVICES_FOURTH_ID,
        ];
    };
});

describe('a service the catalogue says is bookable', function () {
    it('describes it with its own uuid, duration, trailing buffer and staff', function () {
        $service = ($this->describeIn)();

        expect($service->id)->toBe(ServiceFixtures::SERVICE_ID)
            ->and($service->durationMinutes)->toBe(45)
            ->and($service->bufferAfterMinutes)->toBe(10)
            ->and($service->staffIds)->toBe([ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID]);
    });

    it('describes an active service under an unlimited plan however many are active', function () {
        $this->activeIdsOldestFirst = [
            ServiceFixtures::SECOND_SERVICE_ID,
            ServiceFixtures::THIRD_SERVICE_ID,
            BOOKABLE_SERVICES_FOURTH_ID,
            ServiceFixtures::SERVICE_ID,
        ];

        expect(($this->describeIn)()->id)->toBe(ServiceFixtures::SERVICE_ID);
    });

    it('describes one of the oldest active services the limited plan still covers', function (string $serviceId) {
        ($this->offerOnFree)($serviceId);

        expect(($this->describeIn)($serviceId)->id)->toBe($serviceId);
    })->with([
        'the oldest' => ServiceFixtures::SERVICE_ID,
        'the last one inside the limit' => ServiceFixtures::THIRD_SERVICE_ID,
    ]);
});

describe('a service the catalogue refuses', function () {
    it('refuses an active service past the limited plan allowance', function () {
        ($this->offerOnFree)(BOOKABLE_SERVICES_FOURTH_ID);

        expect(fn () => ($this->describeIn)(BOOKABLE_SERVICES_FOURTH_ID))->toThrow(BookableServiceNotFound::class);
    });

    it('refuses a service that is not active', function () {
        $this->offered = ServiceFixtures::service(active: false);
        $this->activeIdsOldestFirst = [];

        expect(fn () => ($this->describeIn)())->toThrow(BookableServiceNotFound::class);
    });

    it('refuses with a failure the transport classifies as not found', function () {
        ($this->offerOnFree)(BOOKABLE_SERVICES_FOURTH_ID);

        $refusal = ($this->refusalFor)(BOOKABLE_SERVICES_FOURTH_ID);

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal?->errorCode())->toBe('bookable_service_not_found')
            ->and($refusal?->kind())->toBe(DomainFailureKind::NotFound)
            ->and($refusal?->getMessage())->toBe('Service ['.BOOKABLE_SERVICES_FOURTH_ID.'] is not bookable.');
    });
});

describe('a service the business does not offer', function () {
    beforeEach(function () {
        $this->offered = null;
    });

    it('refuses it as not bookable', function () {
        expect(fn () => ($this->describeIn)())->toThrow(BookableServiceNotFound::class);
    });

    it('keeps the services domain refusal as the cause', function () {
        expect(($this->refusalFor)()?->getPrevious())->toBeInstanceOf(ServiceNotFound::class);
    });

    it('never asks the catalogue about a service it could not find', function () {
        ($this->refusalFor)();

        expect($this->catalogLookups)->toBe([])
            ->and($this->allowanceLookups)->toBe([]);
    });
});

describe('the business it describes for', function () {
    it('looks the service up and asks the catalogue inside the business it was handed', function () {
        $this->gateway->describe(ServiceFixtures::OTHER_BUSINESS_ID, ServiceFixtures::SERVICE_ID);

        expect($this->serviceLookups)->toBe([[
            'businessId' => ServiceFixtures::OTHER_BUSINESS_ID,
            'id' => ServiceFixtures::SERVICE_ID,
        ]])
            ->and($this->catalogLookups)->toBe([ServiceFixtures::OTHER_BUSINESS_ID])
            ->and($this->allowanceLookups)->toBe([ServiceFixtures::OTHER_BUSINESS_ID]);
    });
});
