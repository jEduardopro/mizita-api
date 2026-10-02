<?php

declare(strict_types=1);

use App\Domains\Platform\Exceptions\ImpersonationConfinedToBusiness;
use App\Domains\Platform\ValueObjects\Impersonation;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Platform\ImpersonationFixtures;

describe('beginning an impersonation', function () {
    it('carries its audit id, the admin, the owner account and the business it was begun for', function () {
        $impersonation = Impersonation::begin(
            ImpersonationFixtures::IMPERSONATION_ID,
            ImpersonationFixtures::ADMIN_ID,
            ImpersonationFixtures::owner(),
            ImpersonationFixtures::now(),
        );

        expect($impersonation->id)->toBe(ImpersonationFixtures::IMPERSONATION_ID)
            ->and($impersonation->adminId)->toBe(ImpersonationFixtures::ADMIN_ID)
            ->and($impersonation->accountId)->toBe(ImpersonationFixtures::ACCOUNT_ID)
            ->and($impersonation->businessId)->toBe(ImpersonationFixtures::BUSINESS_ID)
            ->and($impersonation->businessName)->toBe(ImpersonationFixtures::BUSINESS_NAME)
            ->and($impersonation->ownerName)->toBe(ImpersonationFixtures::OWNER_NAME)
            ->and($impersonation->startedAt)->toEqual(ImpersonationFixtures::now());
    });

    it('expires sixty minutes after it began', function () {
        expect(Impersonation::DURATION_MINUTES)->toBe(60)
            ->and(ImpersonationFixtures::begun()->expiresAt)
            ->toEqual(new DateTimeImmutable(ImpersonationFixtures::EXPIRES_AT));
    });

    it('lasts exactly one hour of real time across a daylight saving change', function (string $utcStart) {
        $start = (new DateTimeImmutable($utcStart))->setTimezone(new DateTimeZone('Europe/Madrid'));

        $impersonation = ImpersonationFixtures::begun($start);

        expect($impersonation->expiresAt->getTimestamp() - $impersonation->startedAt->getTimestamp())->toBe(3600);
    })->with([
        'spring forward, 01:30 local, through the missing 02:00 hour' => ['2026-03-29T00:30:00+00:00'],
        'fall back, the first 02:30 local, into the repeated hour' => ['2026-10-25T00:30:00+00:00'],
    ]);
});

describe('expiry', function () {
    beforeEach(function () {
        $this->impersonation = ImpersonationFixtures::begun();
        $this->expiresAt = new DateTimeImmutable(ImpersonationFixtures::EXPIRES_AT);
    });

    it('is not expired the moment it begins', function () {
        expect($this->impersonation->isExpiredAt(ImpersonationFixtures::now()))->toBeFalse();
    });

    it('is not expired one second before it expires', function () {
        expect($this->impersonation->isExpiredAt($this->expiresAt->modify('-1 second')))->toBeFalse();
    });

    it('is expired at exactly the instant it expires', function () {
        expect($this->impersonation->isExpiredAt($this->expiresAt))->toBeTrue();
    });

    it('stays expired after it expired', function () {
        expect($this->impersonation->isExpiredAt($this->expiresAt->modify('+1 second')))->toBeTrue();
    });

    it('compares instants, not wall clocks, when now is expressed in another zone', function () {
        $sameInstantInMexico = $this->expiresAt->setTimezone(new DateTimeZone('America/Mexico_City'));

        expect($this->impersonation->isExpiredAt($sameInstantInMexico))->toBeTrue()
            ->and($this->impersonation->isExpiredAt($sameInstantInMexico->modify('-1 second')))->toBeFalse();
    });
});

describe('whether the session still backs it', function () {
    beforeEach(function () {
        $this->impersonation = ImpersonationFixtures::begun();
        $this->beforeExpiry = new DateTimeImmutable('2026-09-25T15:59:59+00:00');
    });

    it('holds while the same admin and the same owner are signed in before it expires', function () {
        expect($this->impersonation->isStillValidFor(
            ImpersonationFixtures::ADMIN_ID,
            ImpersonationFixtures::ACCOUNT_ID,
            $this->beforeExpiry,
        ))->toBeTrue();
    });

    it('no longer holds once it expired, even with the same admin and owner', function () {
        expect($this->impersonation->isStillValidFor(
            ImpersonationFixtures::ADMIN_ID,
            ImpersonationFixtures::ACCOUNT_ID,
            new DateTimeImmutable(ImpersonationFixtures::EXPIRES_AT),
        ))->toBeFalse();
    });

    it('does not hold for anyone but the admin who began it', function (?string $signedInAdminId) {
        expect($this->impersonation->isStillValidFor(
            $signedInAdminId,
            ImpersonationFixtures::ACCOUNT_ID,
            $this->beforeExpiry,
        ))->toBeFalse();
    })->with([
        'no admin signed in' => [null],
        'another admin' => [ImpersonationFixtures::OTHER_ADMIN_ID],
        'an empty admin id' => [''],
    ]);

    it('does not hold for anyone but the owner being impersonated', function (?string $signedInAccountId) {
        expect($this->impersonation->isStillValidFor(
            ImpersonationFixtures::ADMIN_ID,
            $signedInAccountId,
            $this->beforeExpiry,
        ))->toBeFalse();
    })->with([
        'no owner signed in' => [null],
        'another account' => [ImpersonationFixtures::OTHER_ACCOUNT_ID],
        'an empty account id' => [''],
    ]);
});

describe('restoring from the session', function () {
    it('round-trips every field of an impersonation that was begun', function () {
        $begun = ImpersonationFixtures::begun();

        $restored = Impersonation::restore(
            id: $begun->id,
            adminId: $begun->adminId,
            accountId: $begun->accountId,
            businessId: $begun->businessId,
            businessName: $begun->businessName,
            ownerName: $begun->ownerName,
            startedAt: $begun->startedAt,
            expiresAt: $begun->expiresAt,
        );

        expect($restored)->toEqual($begun);
    });

    it('keeps the stored expiry instead of recomputing it from the start', function () {
        $restored = Impersonation::restore(
            id: ImpersonationFixtures::IMPERSONATION_ID,
            adminId: ImpersonationFixtures::ADMIN_ID,
            accountId: ImpersonationFixtures::ACCOUNT_ID,
            businessId: ImpersonationFixtures::BUSINESS_ID,
            businessName: ImpersonationFixtures::BUSINESS_NAME,
            ownerName: ImpersonationFixtures::OWNER_NAME,
            startedAt: ImpersonationFixtures::now(),
            expiresAt: new DateTimeImmutable('2026-09-25T15:05:00+00:00'),
        );

        expect($restored->expiresAt)->toEqual(new DateTimeImmutable('2026-09-25T15:05:00+00:00'))
            ->and($restored->isExpiredAt(new DateTimeImmutable('2026-09-25T15:05:00+00:00')))->toBeTrue();
    });
});

describe('the business it may operate', function () {
    beforeEach(function () {
        $this->impersonation = ImpersonationFixtures::begun();
    });

    it('operates the business it was begun for when none is requested', function () {
        expect($this->impersonation->businessToOperate(null))->toBe(ImpersonationFixtures::BUSINESS_ID);
    });

    it('operates the business it was begun for when that same business is requested', function () {
        expect($this->impersonation->businessToOperate(ImpersonationFixtures::BUSINESS_ID))->toBe(ImpersonationFixtures::BUSINESS_ID);
    });

    it('refuses any other business the owner could otherwise switch to', function (string $requestedBusinessId) {
        expect(fn () => $this->impersonation->businessToOperate($requestedBusinessId))
            ->toThrow(ImpersonationConfinedToBusiness::class);
    })->with([
        'another business uuid' => [ImpersonationFixtures::OTHER_BUSINESS_ID],
        'an empty header' => [''],
        'an int primary key' => ['42'],
        'the same uuid with a trailing space' => [ImpersonationFixtures::BUSINESS_ID.' '],
    ]);

    it('refuses as a forbidden business, the same refusal a missing membership gets', function () {
        try {
            $this->impersonation->businessToOperate(ImpersonationFixtures::OTHER_BUSINESS_ID);
        } catch (ImpersonationConfinedToBusiness $refusal) {
            expect($refusal)->toBeInstanceOf(DomainFailure::class)
                ->and($refusal->errorCode())->toBe('business_not_accessible')
                ->and($refusal->kind())->toBe(DomainFailureKind::Forbidden);

            return;
        }

        test()->fail('Another business was operated while impersonating.');
    });
});

describe('the instant it ended', function () {
    beforeEach(function () {
        $this->impersonation = ImpersonationFixtures::begun();
        $this->expiresAt = new DateTimeImmutable(ImpersonationFixtures::EXPIRES_AT);
    });

    it('ended when it was stopped, while the hour had not run out', function (string $stoppedAt) {
        expect($this->impersonation->endedAt(new DateTimeImmutable($stoppedAt)))->toEqual(new DateTimeImmutable($stoppedAt));
    })->with([
        'the moment it began' => [ImpersonationFixtures::NOW],
        'ten minutes in' => ['2026-09-25T15:10:00+00:00'],
        'one second before it expires' => ['2026-09-25T15:59:59+00:00'],
    ]);

    it('ended at its expiry when it was stopped after the hour ran out', function (string $stoppedAt) {
        expect($this->impersonation->endedAt(new DateTimeImmutable($stoppedAt)))->toEqual($this->expiresAt);
    })->with([
        'exactly at the expiry' => [ImpersonationFixtures::EXPIRES_AT],
        'one second after the expiry' => ['2026-09-25T16:00:01+00:00'],
        'the next day' => ['2026-09-26T09:00:00+00:00'],
    ]);

    it('compares instants, not wall clocks, on a fall back day', function (string $stoppedAt, string $expectedEnd) {
        $begunInMadrid = ImpersonationFixtures::begun(
            (new DateTimeImmutable('2026-10-25T00:30:00+00:00'))->setTimezone(new DateTimeZone('Europe/Madrid')),
        );

        $stoppedInMadrid = (new DateTimeImmutable($stoppedAt))->setTimezone(new DateTimeZone('Europe/Madrid'));

        expect($begunInMadrid->endedAt($stoppedInMadrid)->getTimestamp())
            ->toBe((new DateTimeImmutable($expectedEnd))->getTimestamp());
    })->with([
        'stopped at 02:15 local in the repeated hour, earlier on the wall than the 02:30 start' => ['2026-10-25T01:15:00+00:00', '2026-10-25T01:15:00+00:00'],
        'stopped at 02:31 local in the repeated hour, a minute past the start on the wall' => ['2026-10-25T01:31:00+00:00', '2026-10-25T01:30:00+00:00'],
    ]);
});
