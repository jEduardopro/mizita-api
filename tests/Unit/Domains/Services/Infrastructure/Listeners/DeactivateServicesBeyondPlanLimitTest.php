<?php

declare(strict_types=1);

use App\Domains\Services\Application\UseCases\EnforceActiveServiceLimit;
use App\Domains\Services\Exceptions\ServiceSlugAlreadyTaken;
use App\Domains\Services\Infrastructure\Listeners\DeactivateServicesBeyondPlanLimit;
use App\Domains\Subscriptions\Events\SubscriptionEnded;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeTransactionManager;
use Tests\Support\Services\FakeServiceAllowance;
use Tests\Support\Services\FakeServiceRepository;
use Tests\Support\Services\ServiceFixtures;

beforeEach(function () {
    $this->services = (new FakeServiceRepository)->store(...ServiceFixtures::lineup([
        ServiceFixtures::SERVICE_ID,
        ServiceFixtures::SECOND_SERVICE_ID,
        ServiceFixtures::THIRD_SERVICE_ID,
        ServiceFixtures::FOURTH_SERVICE_ID,
    ]));
    $this->allowance = FakeServiceAllowance::free();

    $this->listener = new DeactivateServicesBeyondPlanLimit(
        new EnforceActiveServiceLimit($this->services, $this->allowance, new FakeTransactionManager),
    );

    $this->subscriptionEnded = new SubscriptionEnded(
        subscriptionId: '01930000-0000-7000-8000-0000000000a1',
        businessId: FakeBusinessContext::BUSINESS_ID,
    );
});

it('enforces the limit for the business whose subscription ended', function () {
    $this->listener->handle($this->subscriptionEnded);

    expect($this->allowance->asked)->toBe([FakeBusinessContext::BUSINESS_ID])
        ->and(array_map(static fn ($service) => $service->id, $this->services->saved))->toBe([ServiceFixtures::FOURTH_SERVICE_ID]);
});

it('rethrows a refusal so the queue retries the job', function () {
    $this->services->failingOnSave(ServiceSlugAlreadyTaken::for(ServiceFixtures::SLUG));

    expect(fn () => $this->listener->handle($this->subscriptionEnded))->toThrow(ServiceSlugAlreadyTaken::class);
});

it('runs only once the transaction that ended the subscription has committed', function () {
    expect($this->listener)->toBeInstanceOf(ShouldQueueAfterCommit::class);
});
