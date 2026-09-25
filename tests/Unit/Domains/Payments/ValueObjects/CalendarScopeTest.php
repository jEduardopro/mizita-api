<?php

declare(strict_types=1);

use App\Domains\Payments\ValueObjects\CalendarScope;
use Tests\Support\Payments\PaymentFixtures;

describe('a scope over every calendar', function () {
    it('is not restricted', function () {
        expect(CalendarScope::everyone()->isRestricted())->toBeFalse();
    });

    it('covers the appointment of any team member', function (string $staffMemberId) {
        $appointment = PaymentFixtures::appointmentSnapshot(staffMemberId: $staffMemberId);

        expect(CalendarScope::everyone()->covers($appointment))->toBeTrue();
    })->with([
        'the first' => PaymentFixtures::STAFF_MEMBER_ID,
        'another' => PaymentFixtures::OTHER_STAFF_MEMBER_ID,
    ]);
});

describe('a scope over one team member calendar', function () {
    beforeEach(function () {
        $this->scope = CalendarScope::ownedBy(PaymentFixtures::STAFF_MEMBER_ID);
    });

    it('is restricted', function () {
        expect($this->scope->isRestricted())->toBeTrue();
    });

    it('covers an appointment on that calendar', function () {
        expect($this->scope->covers(PaymentFixtures::appointmentSnapshot()))->toBeTrue();
    });

    it('covers a cancelled appointment on that calendar just the same', function () {
        expect($this->scope->covers(PaymentFixtures::appointmentSnapshot(cancelled: true)))->toBeTrue();
    });

    it('covers no appointment on the calendar of another team member', function () {
        $appointment = PaymentFixtures::appointmentSnapshot(staffMemberId: PaymentFixtures::OTHER_STAFF_MEMBER_ID);

        expect($this->scope->covers($appointment))->toBeFalse();
    });
});
