<?php

declare(strict_types=1);

use App\Domains\Integrations\Application\Dtos\ListCalendarBusyIntervalsInput;
use App\Domains\Integrations\Exceptions\CalendarBusinessNotFound;
use App\Domains\Integrations\Exceptions\CalendarOwnerNotFound;
use App\Domains\Integrations\Exceptions\InvalidBusyWindow;
use App\Shared\Contracts\DomainFailure;
use Tests\Unit\Domains\Integrations\Application\Doubles\IntegrationsFixtures;

it('accepts two uuids and a window that ends after it starts', function () {
    expect(fn () => IntegrationsFixtures::busyInput()->validate())->not->toThrow(Throwable::class);
});

it('accepts uuids written in upper case', function () {
    $input = IntegrationsFixtures::busyInput(
        businessId: strtoupper(IntegrationsFixtures::BUSINESS_ID),
        staffMemberId: strtoupper(IntegrationsFixtures::STAFF_MEMBER_ID),
    );

    expect(fn () => $input->validate())->not->toThrow(Throwable::class);
});

it('accepts a window one second long', function () {
    expect(fn () => IntegrationsFixtures::busyInput('2026-10-01T00:00:00+00:00', '2026-10-01T00:00:01+00:00')->validate())
        ->not->toThrow(Throwable::class);
});

it('compares the window as instants, whatever offset each end carries', function () {
    expect(fn () => IntegrationsFixtures::busyInput('2026-10-01T10:00:00+02:00', '2026-10-01T09:00:00+00:00')->validate())
        ->not->toThrow(Throwable::class);
});

it('rejects an input the caller got wrong', function (ListCalendarBusyIntervalsInput $input, string $exception, string $code) {
    try {
        $input->validate();
        $this->fail('validate() accepted an input it should have refused.');
    } catch (DomainFailure $failure) {
        expect($failure)->toBeInstanceOf($exception)
            ->and($failure->errorCode())->toBe($code);
    }
})->with([
    'integer business id' => [
        fn () => IntegrationsFixtures::busyInput(businessId: '1'),
        CalendarBusinessNotFound::class,
        'business_not_found',
    ],
    'empty business id' => [
        fn () => IntegrationsFixtures::busyInput(businessId: ''),
        CalendarBusinessNotFound::class,
        'business_not_found',
    ],
    'business id with a trailing newline' => [
        fn () => IntegrationsFixtures::busyInput(businessId: IntegrationsFixtures::BUSINESS_ID."\n"),
        CalendarBusinessNotFound::class,
        'business_not_found',
    ],
    'integer staff member id' => [
        fn () => IntegrationsFixtures::busyInput(staffMemberId: '1'),
        CalendarOwnerNotFound::class,
        'staff_member_not_found',
    ],
    'staff member id with spaces' => [
        fn () => IntegrationsFixtures::busyInput(staffMemberId: ' '.IntegrationsFixtures::STAFF_MEMBER_ID.' '),
        CalendarOwnerNotFound::class,
        'staff_member_not_found',
    ],
    'empty window' => [
        fn () => IntegrationsFixtures::busyInput('2026-10-01T00:00:00+00:00', '2026-10-01T00:00:00+00:00'),
        InvalidBusyWindow::class,
        'invalid_busy_window',
    ],
    'same instant in two offsets' => [
        fn () => IntegrationsFixtures::busyInput('2026-10-01T02:00:00+02:00', '2026-10-01T00:00:00+00:00'),
        InvalidBusyWindow::class,
        'invalid_busy_window',
    ],
    'reversed window' => [
        fn () => IntegrationsFixtures::busyInput('2026-10-08T00:00:00+00:00', '2026-10-01T00:00:00+00:00'),
        InvalidBusyWindow::class,
        'invalid_busy_window',
    ],
]);

it('checks the business before the staff member', function () {
    expect(fn () => IntegrationsFixtures::busyInput(businessId: 'x', staffMemberId: 'y')->validate())
        ->toThrow(CalendarBusinessNotFound::class);
});

it('checks the staff member before the window', function () {
    expect(fn () => IntegrationsFixtures::busyInput('2026-10-08T00:00:00+00:00', '2026-10-01T00:00:00+00:00', staffMemberId: 'y')->validate())
        ->toThrow(CalendarOwnerNotFound::class);
});
