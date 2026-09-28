<?php

declare(strict_types=1);

use App\Domains\Services\Application\Services\ActiveServiceQuota;
use App\Domains\Services\Exceptions\ActiveServiceLimitReached;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Services\FakeServiceAllowance;
use Tests\Support\Services\FakeServiceRepository;
use Tests\Support\Services\ServiceFixtures;

beforeEach(function () {
    $this->services = new FakeServiceRepository;

    $this->ensureRoom = fn (FakeServiceAllowance $allowance) => (new ActiveServiceQuota($this->services, $allowance))
        ->ensureRoomFor(FakeBusinessContext::BUSINESS_ID);

    $this->withActive = fn (string ...$ids) => $this->services->store(...ServiceFixtures::lineup(array_values($ids)));
});

describe('a plan without a limit', function () {
    it('makes room without counting and without taking the lock', function () {
        ($this->withActive)(
            ServiceFixtures::SERVICE_ID,
            ServiceFixtures::SECOND_SERVICE_ID,
            ServiceFixtures::THIRD_SERVICE_ID,
            ServiceFixtures::FOURTH_SERVICE_ID,
        );

        expect(fn () => ($this->ensureRoom)(FakeServiceAllowance::unlimited()))->not->toThrow(Throwable::class)
            ->and($this->services->locks)->toBe([])
            ->and($this->services->countsUnderLock)->toBe([]);
    });
});

describe('a plan with a limit', function () {
    it('makes room while the business is below the limit', function (array $activeIds) {
        ($this->withActive)(...$activeIds);

        expect(fn () => ($this->ensureRoom)(FakeServiceAllowance::free()))->not->toThrow(Throwable::class);
    })->with([
        'no active service' => [[]],
        'one active service' => [[ServiceFixtures::SERVICE_ID]],
        'one below the limit' => [[ServiceFixtures::SERVICE_ID, ServiceFixtures::SECOND_SERVICE_ID]],
    ]);

    it('refuses once the business has reached or passed the limit', function (array $activeIds) {
        ($this->withActive)(...$activeIds);

        expect(fn () => ($this->ensureRoom)(FakeServiceAllowance::free()))->toThrow(ActiveServiceLimitReached::class);
    })->with([
        'exactly at the limit' => [[
            ServiceFixtures::SERVICE_ID,
            ServiceFixtures::SECOND_SERVICE_ID,
            ServiceFixtures::THIRD_SERVICE_ID,
        ]],
        'past the limit after a downgrade' => [[
            ServiceFixtures::SERVICE_ID,
            ServiceFixtures::SECOND_SERVICE_ID,
            ServiceFixtures::THIRD_SERVICE_ID,
            ServiceFixtures::FOURTH_SERVICE_ID,
        ]],
    ]);

    it('refuses as a forbidden domain failure with a stable code', function () {
        ($this->withActive)(ServiceFixtures::SERVICE_ID);

        try {
            ($this->ensureRoom)(FakeServiceAllowance::limitedTo(1));
            $this->fail('The quota made room past its limit.');
        } catch (ActiveServiceLimitReached $refusal) {
            expect($refusal)->toBeInstanceOf(DomainFailure::class)
                ->and($refusal->errorCode())->toBe('active_service_limit_reached')
                ->and($refusal->kind())->toBe(DomainFailureKind::Forbidden);
        }
    });

    it('refuses every activation when the limit is zero', function () {
        expect(fn () => ($this->ensureRoom)(FakeServiceAllowance::limitedTo(0)))->toThrow(ActiveServiceLimitReached::class);
    });

    it('leaves hidden services out of the count', function () {
        $this->services->store(...ServiceFixtures::lineup([
            ServiceFixtures::SERVICE_ID,
            ServiceFixtures::SECOND_SERVICE_ID,
            ServiceFixtures::THIRD_SERVICE_ID,
        ], active: false));

        expect(fn () => ($this->ensureRoom)(FakeServiceAllowance::free()))->not->toThrow(Throwable::class);
    });

    it('leaves the services of another business out of the count', function () {
        $this->services->store(...ServiceFixtures::lineup([
            ServiceFixtures::SERVICE_ID,
            ServiceFixtures::SECOND_SERVICE_ID,
            ServiceFixtures::THIRD_SERVICE_ID,
        ], businessId: ServiceFixtures::OTHER_BUSINESS_ID));

        expect(fn () => ($this->ensureRoom)(FakeServiceAllowance::free()))->not->toThrow(Throwable::class);
    });

    it('takes the activation lock of the business before it counts', function () {
        ($this->ensureRoom)(FakeServiceAllowance::free());

        expect($this->services->locks)->toBe([FakeBusinessContext::BUSINESS_ID])
            ->and($this->services->countsUnderLock)->toBe([true]);
    });
});

it('asks the allowance about the business it was given', function () {
    $allowance = FakeServiceAllowance::free();

    ($this->ensureRoom)($allowance);

    expect($allowance->asked)->toBe([FakeBusinessContext::BUSINESS_ID]);
});
