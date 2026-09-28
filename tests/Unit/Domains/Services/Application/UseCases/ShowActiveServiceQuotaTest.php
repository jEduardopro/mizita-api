<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\ActiveServiceQuotaData;
use App\Domains\Services\Application\UseCases\ShowActiveServiceQuota;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Services\FakeServiceAllowance;
use Tests\Support\Services\FakeServiceRepository;
use Tests\Support\Services\ServiceFixtures;

beforeEach(function () {
    $this->services = (new FakeServiceRepository)->store(
        ...ServiceFixtures::lineup([ServiceFixtures::SERVICE_ID, ServiceFixtures::SECOND_SERVICE_ID]),
        ...ServiceFixtures::lineup([ServiceFixtures::THIRD_SERVICE_ID], active: false),
        ...ServiceFixtures::lineup([
            ServiceFixtures::FOURTH_SERVICE_ID,
            ServiceFixtures::FIFTH_SERVICE_ID,
        ], businessId: ServiceFixtures::OTHER_BUSINESS_ID),
    );

    $this->show = fn (FakeServiceAllowance $allowance) => (new ShowActiveServiceQuota(
        $this->services,
        $allowance,
        new FakeBusinessContext,
    ))->handle();
});

it('reports the active services of the business in context against the plan limit', function () {
    $quota = ($this->show)(FakeServiceAllowance::free())->value();

    expect($quota)->toBeInstanceOf(ActiveServiceQuotaData::class)
        ->and($quota->activeCount)->toBe(2)
        ->and($quota->activeLimit)->toBe(FakeServiceAllowance::FREE_LIMIT);
});

it('reports no limit for a plan without one', function () {
    $quota = ($this->show)(FakeServiceAllowance::unlimited())->value();

    expect($quota->activeCount)->toBe(2)
        ->and($quota->activeLimit)->toBeNull();
});

it('asks only about the business in context', function () {
    $allowance = FakeServiceAllowance::free();

    ($this->show)($allowance);

    expect($allowance->asked)->toBe([FakeBusinessContext::BUSINESS_ID])
        ->and(array_unique($this->services->businessIdsSeen))->toBe([FakeBusinessContext::BUSINESS_ID]);
});

it('reads the quota without taking the activation lock', function () {
    ($this->show)(FakeServiceAllowance::free());

    expect($this->services->locks)->toBe([]);
});
