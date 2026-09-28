<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\EnforceActiveServiceLimitInput;
use App\Domains\Services\Application\UseCases\EnforceActiveServiceLimit;
use App\Domains\Services\Exceptions\ServiceSlugAlreadyTaken;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeTransactionManager;
use Tests\Support\Services\FakeServiceAllowance;
use Tests\Support\Services\FakeServiceRepository;
use Tests\Support\Services\ServiceFixtures;

beforeEach(function () {
    $this->transactions = new FakeTransactionManager;
    $this->services = (new FakeServiceRepository)->observing($this->transactions)->store(...ServiceFixtures::lineup([
        ServiceFixtures::SERVICE_ID,
        ServiceFixtures::SECOND_SERVICE_ID,
        ServiceFixtures::THIRD_SERVICE_ID,
        ServiceFixtures::FOURTH_SERVICE_ID,
        ServiceFixtures::FIFTH_SERVICE_ID,
    ]));

    $this->enforce = fn (FakeServiceAllowance $allowance, string $businessId = FakeBusinessContext::BUSINESS_ID) => (new EnforceActiveServiceLimit(
        $this->services,
        $allowance,
        $this->transactions,
    ))->handle(new EnforceActiveServiceLimitInput($businessId));

    $this->isActive = fn (string $serviceId, string $businessId = FakeBusinessContext::BUSINESS_ID): bool => $this->services
        ->findForBusiness($businessId, $serviceId)
        ->isActive();
});

describe('a plan with a limit', function () {
    it('answers with how many services it hid', function () {
        expect(($this->enforce)(FakeServiceAllowance::free())->value())->toBe(2);
    });

    it('hides the newest active services beyond the limit and keeps the oldest visible', function () {
        ($this->enforce)(FakeServiceAllowance::free());

        expect(($this->isActive)(ServiceFixtures::SERVICE_ID))->toBeTrue()
            ->and(($this->isActive)(ServiceFixtures::SECOND_SERVICE_ID))->toBeTrue()
            ->and(($this->isActive)(ServiceFixtures::THIRD_SERVICE_ID))->toBeTrue()
            ->and(($this->isActive)(ServiceFixtures::FOURTH_SERVICE_ID))->toBeFalse()
            ->and(($this->isActive)(ServiceFixtures::FIFTH_SERVICE_ID))->toBeFalse();
    });

    it('saves exactly the services it hid', function () {
        ($this->enforce)(FakeServiceAllowance::free());

        expect(array_map(static fn ($service) => $service->id, $this->services->saved))
            ->toBe([ServiceFixtures::FOURTH_SERVICE_ID, ServiceFixtures::FIFTH_SERVICE_ID]);
    });

    it('hides and saves nothing when the business is within the limit', function () {
        $services = (new FakeServiceRepository)->store(...ServiceFixtures::lineup([
            ServiceFixtures::SERVICE_ID,
            ServiceFixtures::SECOND_SERVICE_ID,
            ServiceFixtures::THIRD_SERVICE_ID,
        ]));

        $response = (new EnforceActiveServiceLimit($services, FakeServiceAllowance::free(), new FakeTransactionManager))
            ->handle(new EnforceActiveServiceLimitInput(FakeBusinessContext::BUSINESS_ID));

        expect($response->value())->toBe(0)
            ->and($services->saved)->toBe([]);
    });

    it('leaves hidden services where they are', function () {
        $services = (new FakeServiceRepository)->store(
            ...ServiceFixtures::lineup([ServiceFixtures::SERVICE_ID]),
            ...ServiceFixtures::lineup([
                ServiceFixtures::SECOND_SERVICE_ID,
                ServiceFixtures::THIRD_SERVICE_ID,
                ServiceFixtures::FOURTH_SERVICE_ID,
            ], active: false),
        );

        $response = (new EnforceActiveServiceLimit($services, FakeServiceAllowance::free(), new FakeTransactionManager))
            ->handle(new EnforceActiveServiceLimitInput(FakeBusinessContext::BUSINESS_ID));

        expect($response->value())->toBe(0)
            ->and($services->saved)->toBe([]);
    });

    it('touches only the business it was given', function () {
        $foreignIds = [
            '01930000-0000-7000-8000-0000000000f1',
            '01930000-0000-7000-8000-0000000000f2',
            '01930000-0000-7000-8000-0000000000f3',
            '01930000-0000-7000-8000-0000000000f4',
        ];
        $this->services->store(...ServiceFixtures::lineup($foreignIds, businessId: ServiceFixtures::OTHER_BUSINESS_ID));

        ($this->enforce)(FakeServiceAllowance::free());
        $businessesTouched = array_values(array_unique($this->services->businessIdsSeen));

        expect($businessesTouched)->toBe([FakeBusinessContext::BUSINESS_ID])
            ->and(($this->isActive)($foreignIds[3], ServiceFixtures::OTHER_BUSINESS_ID))->toBeTrue();
    });

    it('picks the surplus under the activation lock, inside one transaction', function () {
        ($this->enforce)(FakeServiceAllowance::free());

        expect($this->transactions->runs())->toBe(1)
            ->and($this->services->locks)->toBe([FakeBusinessContext::BUSINESS_ID])
            ->and($this->services->locksInsideTransaction)->toBe([true])
            ->and($this->services->savesInsideTransaction)->toBe([true, true]);
    });

    it('returns the failure when a save is refused', function () {
        $this->services->failingOnSave(ServiceSlugAlreadyTaken::for(ServiceFixtures::SLUG));

        $response = ($this->enforce)(FakeServiceAllowance::free());

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('service_slug_taken')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });
});

describe('a plan without a limit', function () {
    it('hides nothing, takes no lock and opens no transaction', function () {
        $response = ($this->enforce)(FakeServiceAllowance::unlimited());

        expect($response->value())->toBe(0)
            ->and($this->services->saved)->toBe([])
            ->and($this->services->locks)->toBe([])
            ->and($this->transactions->runs())->toBe(0)
            ->and(($this->isActive)(ServiceFixtures::FIFTH_SERVICE_ID))->toBeTrue();
    });
});
