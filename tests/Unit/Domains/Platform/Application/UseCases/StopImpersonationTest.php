<?php

declare(strict_types=1);

use App\Domains\Platform\Application\UseCases\StopImpersonation;
use Tests\Support\FakeClock;
use Tests\Support\Platform\ImpersonationFixtures;
use Tests\Support\Platform\RecordingImpersonationAuditTrail;
use Tests\Support\Platform\RecordingImpersonationSession;

beforeEach(function () {
    $this->session = new RecordingImpersonationSession;
    $this->auditTrail = new RecordingImpersonationAuditTrail;
    $this->clock = new FakeClock(new DateTimeImmutable('2026-09-25T15:10:00+00:00'));
    $this->stop = new StopImpersonation($this->session, $this->auditTrail, $this->clock);
});

describe('with an impersonation running', function () {
    beforeEach(function () {
        $this->session->holding(ImpersonationFixtures::begun());
    });

    it('ends the impersonation session exactly once', function () {
        $this->stop->handle();

        expect($this->session->endings)->toBe(1)
            ->and($this->session->current())->toBeNull();
    });

    it('closes the audit row of the impersonation it ended, at the instant the clock reads', function () {
        $this->stop->handle();

        expect($this->auditTrail->ended)->toEqual([
            ['impersonationId' => ImpersonationFixtures::IMPERSONATION_ID, 'endedAt' => new DateTimeImmutable('2026-09-25T15:10:00+00:00')],
        ]);
    });

    it('closes the row at the expiry when the hour ran out before anyone stopped it', function () {
        $this->clock->advance('PT3H');

        $this->stop->handle();

        expect($this->auditTrail->ended[0]['endedAt'])->toEqual(new DateTimeImmutable(ImpersonationFixtures::EXPIRES_AT));
    });

    it('closes the row only once when stopped twice', function () {
        $this->stop->handle();
        $this->stop->handle();

        expect($this->auditTrail->ended)->toHaveCount(1);
    });

    it('succeeds with nothing to hand back', function () {
        $response = $this->stop->handle();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull();
    });

    it('starts nothing while stopping', function () {
        $this->stop->handle();

        expect($this->session->started)->toBe([])
            ->and($this->auditTrail->started)->toBe([]);
    });
});

describe('with no impersonation running', function () {
    it('still ends the session, so a half stored one is cleared', function () {
        $this->stop->handle();

        expect($this->session->endings)->toBe(1);
    });

    it('closes no audit row', function () {
        $this->stop->handle();

        expect($this->auditTrail->ended)->toBe([]);
    });

    it('succeeds with nothing to hand back', function () {
        $response = $this->stop->handle();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull();
    });
});
