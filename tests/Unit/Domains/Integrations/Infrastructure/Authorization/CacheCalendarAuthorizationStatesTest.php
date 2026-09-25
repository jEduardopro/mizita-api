<?php

declare(strict_types=1);

use App\Domains\Integrations\Exceptions\CalendarAuthorizationStateInvalid;
use App\Domains\Integrations\Infrastructure\Authorization\CacheCalendarAuthorizationStates;
use App\Domains\Integrations\ValueObjects\PendingAuthorization;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Carbon;
use Tests\Support\FakeBusinessContext;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\IntegrationsFixtures;

beforeEach(function () {
    Carbon::setTestNow(IntegrationsFixtures::NOW);

    $this->store = new ArrayStore;
    $this->states = new CacheCalendarAuthorizationStates(new Repository($this->store));
    $this->pending = new PendingAuthorization(
        accountId: '01930000-0000-7000-8000-0000000000a1',
        businessId: FakeBusinessContext::BUSINESS_ID,
        staffMemberId: IntegrationsFixtures::STAFF_MEMBER_ID,
    );

    $this->refusalOf = function (string $state): ?CalendarAuthorizationStateInvalid {
        try {
            $this->states->consume($state);
        } catch (CalendarAuthorizationStateInvalid $refusal) {
            return $refusal;
        }

        return null;
    };
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('issuing a state', function () {
    it('hands out 256 bits of randomness as 64 lowercase hex characters', function () {
        expect($this->states->issue($this->pending))->toMatch('/\A[0-9a-f]{64}\z/');
    });

    it('never hands out the same state twice', function () {
        expect($this->states->issue($this->pending))->not->toBe($this->states->issue($this->pending));
    });

    it('stores the pending authorization under the sha256 of the state', function () {
        $state = $this->states->issue($this->pending);

        expect(array_keys($this->store->all()))
            ->toBe(['integrations:calendar-authorization:pending:'.hash('sha256', $state)]);
    });

    it('never uses the raw state as a cache key', function () {
        $state = $this->states->issue($this->pending);

        foreach (array_keys($this->store->all()) as $key) {
            expect($key)->not->toContain($state);
        }
    });

    it('keeps nothing but the three identities in the cache', function () {
        $this->states->issue($this->pending);

        expect(array_values($this->store->all())[0]['value'])->toBe([
            'account_id' => '01930000-0000-7000-8000-0000000000a1',
            'business_id' => FakeBusinessContext::BUSINESS_ID,
            'staff_member_id' => IntegrationsFixtures::STAFF_MEMBER_ID,
        ]);
    });
});

describe('consuming a state', function () {
    it('gives back the authorization the state was issued for', function () {
        $consumed = $this->states->consume($this->states->issue($this->pending));

        expect($consumed)->toBeInstanceOf(PendingAuthorization::class)
            ->and($consumed->accountId)->toBe('01930000-0000-7000-8000-0000000000a1')
            ->and($consumed->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($consumed->staffMemberId)->toBe(IntegrationsFixtures::STAFF_MEMBER_ID);
    });

    it('still accepts a state one second before its ten minutes are up', function () {
        $state = $this->states->issue($this->pending);

        Carbon::setTestNow(Carbon::parse(IntegrationsFixtures::NOW)->addSeconds(599));

        expect($this->states->consume($state)->staffMemberId)->toBe(IntegrationsFixtures::STAFF_MEMBER_ID);
    });

    it('forgets the pending authorization once consumed', function () {
        $state = $this->states->issue($this->pending);

        $this->states->consume($state);

        expect($this->store->get('integrations:calendar-authorization:pending:'.hash('sha256', $state)))->toBeNull();
    });
});

describe('refusing a state', function () {
    it('refuses a state the second time it is presented', function () {
        $state = $this->states->issue($this->pending);
        $this->states->consume($state);

        expect(fn () => $this->states->consume($state))->toThrow(CalendarAuthorizationStateInvalid::class);
    });

    it('refuses a replay with a failure the transport can classify', function () {
        $state = $this->states->issue($this->pending);
        $this->states->consume($state);

        $refusal = ($this->refusalOf)($state);

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal?->errorCode())->toBe('calendar_authorization_state_invalid')
            ->and($refusal?->kind())->toBe(DomainFailureKind::Invalid);
    });

    it('refuses a state once its ten minutes are up', function () {
        $state = $this->states->issue($this->pending);

        Carbon::setTestNow(Carbon::parse(IntegrationsFixtures::NOW)->addMinutes(10));

        expect(($this->refusalOf)($state)?->errorCode())->toBe('calendar_authorization_state_invalid');
    });

    it('refuses a state that was never issued', function (string $state) {
        $this->states->issue($this->pending);

        expect(($this->refusalOf)($state)?->errorCode())->toBe('calendar_authorization_state_invalid');
    })->with([
        'an empty string' => [''],
        'whitespace' => ['   '],
        'a well formed stranger' => [str_repeat('ab', 32)],
    ]);

    it('refuses the issued state spelled in uppercase', function () {
        $state = $this->states->issue($this->pending);

        expect(($this->refusalOf)(strtoupper($state))?->errorCode())->toBe('calendar_authorization_state_invalid');
    });

    it('never leaks the state into the refusal message', function () {
        $state = $this->states->issue($this->pending);
        $this->states->consume($state);

        expect(($this->refusalOf)($state)?->getMessage())->not->toContain($state);
    });
});
