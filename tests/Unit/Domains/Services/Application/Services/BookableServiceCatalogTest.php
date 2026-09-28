<?php

declare(strict_types=1);

use App\Domains\Services\Application\Services\BookableServiceCatalog;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Services\FakeServiceAllowance;
use Tests\Support\Services\FakeServiceRepository;
use Tests\Support\Services\ServiceFixtures;

beforeEach(function () {
    $this->services = (new FakeServiceRepository)->store(...ServiceFixtures::lineup([
        ServiceFixtures::SERVICE_ID,
        ServiceFixtures::SECOND_SERVICE_ID,
        ServiceFixtures::THIRD_SERVICE_ID,
        ServiceFixtures::FOURTH_SERVICE_ID,
        ServiceFixtures::FIFTH_SERVICE_ID,
    ]));

    $this->catalog = fn (FakeServiceAllowance $allowance) => new BookableServiceCatalog($this->services, $allowance);
});

describe('the bookable services of a business', function () {
    it('offers only the oldest active services the plan allows', function () {
        expect(($this->catalog)(FakeServiceAllowance::free())->bookableIdsFor(FakeBusinessContext::BUSINESS_ID))->toBe([
            ServiceFixtures::SERVICE_ID,
            ServiceFixtures::SECOND_SERVICE_ID,
            ServiceFixtures::THIRD_SERVICE_ID,
        ]);
    });

    it('offers every active service, oldest first, on a plan without a limit', function () {
        expect(($this->catalog)(FakeServiceAllowance::unlimited())->bookableIdsFor(FakeBusinessContext::BUSINESS_ID))->toBe([
            ServiceFixtures::SERVICE_ID,
            ServiceFixtures::SECOND_SERVICE_ID,
            ServiceFixtures::THIRD_SERVICE_ID,
            ServiceFixtures::FOURTH_SERVICE_ID,
            ServiceFixtures::FIFTH_SERVICE_ID,
        ]);
    });

    it('offers every active service when there are fewer than the limit', function () {
        $services = (new FakeServiceRepository)->store(...ServiceFixtures::lineup([
            ServiceFixtures::SERVICE_ID,
            ServiceFixtures::SECOND_SERVICE_ID,
        ]));

        expect((new BookableServiceCatalog($services, FakeServiceAllowance::free()))->bookableIdsFor(FakeBusinessContext::BUSINESS_ID))
            ->toBe([ServiceFixtures::SERVICE_ID, ServiceFixtures::SECOND_SERVICE_ID]);
    });

    it('offers nothing when the business has no active service', function () {
        $services = (new FakeServiceRepository)->store(...ServiceFixtures::lineup([ServiceFixtures::SERVICE_ID], active: false));

        expect((new BookableServiceCatalog($services, FakeServiceAllowance::unlimited()))->bookableIdsFor(FakeBusinessContext::BUSINESS_ID))
            ->toBe([]);
    });

    it('never offers a hidden service, even with room under the limit', function () {
        $services = (new FakeServiceRepository)->store(
            ...ServiceFixtures::lineup([ServiceFixtures::SERVICE_ID]),
            ...ServiceFixtures::lineup([ServiceFixtures::SECOND_SERVICE_ID], active: false),
        );

        expect((new BookableServiceCatalog($services, FakeServiceAllowance::free()))->bookableIdsFor(FakeBusinessContext::BUSINESS_ID))
            ->toBe([ServiceFixtures::SERVICE_ID]);
    });

    it('never offers the services of another business', function () {
        $foreignServiceId = '01930000-0000-7000-8000-0000000000f1';
        $this->services->store(...ServiceFixtures::lineup([$foreignServiceId], businessId: ServiceFixtures::OTHER_BUSINESS_ID));

        expect(($this->catalog)(FakeServiceAllowance::unlimited())->bookableIdsFor(FakeBusinessContext::BUSINESS_ID))
            ->not->toContain($foreignServiceId);
    });

    it('asks the allowance about the business it was given', function () {
        $allowance = FakeServiceAllowance::free();

        ($this->catalog)($allowance)->bookableIdsFor(FakeBusinessContext::BUSINESS_ID);

        expect($allowance->asked)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });
});

describe('whether one service is bookable', function () {
    it('books a service within the plan limit', function (string $serviceId) {
        expect(($this->catalog)(FakeServiceAllowance::free())->isBookable(FakeBusinessContext::BUSINESS_ID, $serviceId))->toBeTrue();
    })->with([
        'the oldest' => ServiceFixtures::SERVICE_ID,
        'the last one the plan allows' => ServiceFixtures::THIRD_SERVICE_ID,
    ]);

    it('refuses an active service beyond the plan limit', function (string $serviceId) {
        expect(($this->catalog)(FakeServiceAllowance::free())->isBookable(FakeBusinessContext::BUSINESS_ID, $serviceId))->toBeFalse();
    })->with([
        'the first past the limit' => ServiceFixtures::FOURTH_SERVICE_ID,
        'the newest' => ServiceFixtures::FIFTH_SERVICE_ID,
    ]);

    it('books any active service on a plan without a limit', function () {
        expect(($this->catalog)(FakeServiceAllowance::unlimited())->isBookable(FakeBusinessContext::BUSINESS_ID, ServiceFixtures::FIFTH_SERVICE_ID))->toBeTrue();
    });

    it('refuses a hidden service', function () {
        $services = (new FakeServiceRepository)->store(...ServiceFixtures::lineup([ServiceFixtures::SERVICE_ID], active: false));

        expect((new BookableServiceCatalog($services, FakeServiceAllowance::unlimited()))->isBookable(FakeBusinessContext::BUSINESS_ID, ServiceFixtures::SERVICE_ID))
            ->toBeFalse();
    });

    it('refuses a service of another business asked for under this one', function () {
        expect(($this->catalog)(FakeServiceAllowance::unlimited())->isBookable(ServiceFixtures::OTHER_BUSINESS_ID, ServiceFixtures::SERVICE_ID))->toBeFalse();
    });

    it('refuses a service nobody has', function () {
        expect(($this->catalog)(FakeServiceAllowance::unlimited())->isBookable(FakeBusinessContext::BUSINESS_ID, '01930000-0000-7000-8000-0000000000ff'))->toBeFalse();
    });
});
