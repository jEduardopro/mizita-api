<?php

declare(strict_types=1);

use App\Domains\Businesses\ValueObjects\BookingPolicyPreferences;
use App\Domains\Businesses\ValueObjects\BookingPolicySnapshot;

function submittedBookingPolicy(
    int $leadTimeMinutes = 60,
    ?int $bookingWindowMinutes = 43200,
    int $slotGranularityMinutes = 30,
    ?int $cancellationWindowMinutes = 240,
    ?string $policyMessage = 'Cancela con cuatro horas de antelación.',
    bool $displayOnBookingPage = true,
): BookingPolicyPreferences {
    return new BookingPolicyPreferences(
        leadTimeMinutes: $leadTimeMinutes,
        bookingWindowMinutes: $bookingWindowMinutes,
        slotGranularityMinutes: $slotGranularityMinutes,
        cancellationWindowMinutes: $cancellationWindowMinutes,
        policyMessage: $policyMessage,
        displayOnBookingPage: $displayOnBookingPage,
    );
}

function storedBookingPolicy(
    int $leadTimeMinutes = 60,
    ?int $bookingWindowMinutes = 43200,
    int $slotGranularityMinutes = 30,
    ?int $cancellationWindowMinutes = 240,
    ?string $policyMessage = 'Cancela con cuatro horas de antelación.',
    bool $displayOnBookingPage = true,
): BookingPolicySnapshot {
    return new BookingPolicySnapshot(
        leadTimeMinutes: $leadTimeMinutes,
        bookingWindowMinutes: $bookingWindowMinutes,
        slotGranularityMinutes: $slotGranularityMinutes,
        cancellationWindowMinutes: $cancellationWindowMinutes,
        policyMessage: $policyMessage,
        displayOnBookingPage: $displayOnBookingPage,
    );
}

describe('changesAnythingOf', function () {
    it('changes nothing when the same policy is resubmitted', function () {
        expect(submittedBookingPolicy()->changesAnythingOf(storedBookingPolicy()))->toBeFalse();
    });

    it('changes the policy when the lead time differs', function (int $submitted, int $current) {
        expect(submittedBookingPolicy(leadTimeMinutes: $submitted)->changesAnythingOf(storedBookingPolicy(leadTimeMinutes: $current)))
            ->toBeTrue();
    })->with([
        'longer' => [90, 60],
        'shorter' => [30, 60],
        'removed' => [0, 60],
        'set from none' => [60, 0],
        'by a single minute' => [61, 60],
    ]);

    it('changes the policy when the booking window differs', function (?int $submitted, ?int $current) {
        expect(submittedBookingPolicy(bookingWindowMinutes: $submitted)->changesAnythingOf(storedBookingPolicy(bookingWindowMinutes: $current)))
            ->toBeTrue();
    })->with([
        'shorter' => [10080, 43200],
        'longer' => [86400, 43200],
        'made unlimited' => [null, 43200],
        'limited from unlimited' => [43200, null],
        'zero against unlimited' => [0, null],
        'unlimited against zero' => [null, 0],
    ]);

    it('changes nothing when both booking windows are unlimited', function () {
        expect(submittedBookingPolicy(bookingWindowMinutes: null)->changesAnythingOf(storedBookingPolicy(bookingWindowMinutes: null)))
            ->toBeFalse();
    });

    it('changes the policy when the granularity differs', function (int $submitted, int $current) {
        expect(submittedBookingPolicy(slotGranularityMinutes: $submitted)->changesAnythingOf(storedBookingPolicy(slotGranularityMinutes: $current)))
            ->toBeTrue();
    })->with([
        'finer' => [15, 30],
        'coarser' => [60, 30],
    ]);

    it('changes the policy when the cancellation window differs', function (?int $submitted, ?int $current) {
        expect(submittedBookingPolicy(cancellationWindowMinutes: $submitted)->changesAnythingOf(storedBookingPolicy(cancellationWindowMinutes: $current)))
            ->toBeTrue();
    })->with([
        'shorter' => [60, 240],
        'longer' => [1440, 240],
        'cancellation withdrawn' => [null, 240],
        'cancellation offered from none' => [240, null],
        'zero against none' => [0, null],
        'none against zero' => [null, 0],
    ]);

    it('changes nothing when neither policy lets a customer cancel', function () {
        expect(submittedBookingPolicy(cancellationWindowMinutes: null)->changesAnythingOf(storedBookingPolicy(cancellationWindowMinutes: null)))
            ->toBeFalse();
    });

    it('changes the policy when the message differs', function (?string $submitted, ?string $current) {
        expect(submittedBookingPolicy(policyMessage: $submitted)->changesAnythingOf(storedBookingPolicy(policyMessage: $current)))
            ->toBeTrue();
    })->with([
        'another message' => ['Llega cinco minutos antes.', 'Cancela con cuatro horas de antelación.'],
        'message removed' => [null, 'Cancela con cuatro horas de antelación.'],
        'message emptied' => ['', 'Cancela con cuatro horas de antelación.'],
        'message set from none' => ['Cancela con cuatro horas de antelación.', null],
        'another letter case' => ['CANCELA CON CUATRO HORAS DE ANTELACIÓN.', 'Cancela con cuatro horas de antelación.'],
        'an accent dropped' => ['Cancela con cuatro horas de antelacion.', 'Cancela con cuatro horas de antelación.'],
        'inner spacing changed' => ['Cancela con  cuatro horas de antelación.', 'Cancela con cuatro horas de antelación.'],
    ]);

    it('changes nothing when the messages differ only in surrounding whitespace or in how they say none', function (?string $submitted, ?string $current) {
        expect(submittedBookingPolicy(policyMessage: $submitted)->changesAnythingOf(storedBookingPolicy(policyMessage: $current)))
            ->toBeFalse();
    })->with([
        'padded with spaces' => ['  Cancela con cuatro horas de antelación.  ', 'Cancela con cuatro horas de antelación.'],
        'padded with a newline and a tab' => ["\n\tCancela con cuatro horas de antelación.\n", 'Cancela con cuatro horas de antelación.'],
        'stored padded' => ['Cancela con cuatro horas de antelación.', ' Cancela con cuatro horas de antelación. '],
        'empty against none' => ['', null],
        'whitespace against none' => ['   ', null],
        'none against empty' => [null, ''],
        'none against none' => [null, null],
    ]);

    it('changes the policy when the booking page display differs', function (bool $submitted, bool $current) {
        expect(submittedBookingPolicy(displayOnBookingPage: $submitted)->changesAnythingOf(storedBookingPolicy(displayOnBookingPage: $current)))
            ->toBeTrue();
    })->with([
        'hidden' => [false, true],
        'shown' => [true, false],
    ]);

    it('changes the policy when every field differs at once', function () {
        expect(submittedBookingPolicy(
            leadTimeMinutes: 90,
            bookingWindowMinutes: null,
            slotGranularityMinutes: 15,
            cancellationWindowMinutes: null,
            policyMessage: null,
            displayOnBookingPage: false,
        )->changesAnythingOf(storedBookingPolicy()))->toBeTrue();
    });
});
