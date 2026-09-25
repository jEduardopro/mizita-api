<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\Dtos\GuestBookingData;
use App\Domains\Appointments\Application\Presenters\GuestBookingPresenter;
use App\Domains\Appointments\Application\Services\GuestBookingFinder;
use App\Domains\Appointments\Application\UseCases\CancelGuestBooking;
use App\Domains\Appointments\Contracts\CancellationPolicy;
use App\Domains\Appointments\Events\AppointmentCancelled;
use App\Domains\Appointments\Services\AppointmentChangeWindow;
use App\Domains\Appointments\ValueObjects\AppointmentStatus;
use App\Domains\Appointments\ValueObjects\CancellationRule;
use App\Domains\Appointments\ValueObjects\Canceller;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\Support\Appointments\AppointmentFixtures;
use Tests\Support\Appointments\AppointmentJournal;
use Tests\Support\Appointments\FakeAppointmentRepository;
use Tests\Support\Appointments\FakeCustomerDirectory;
use Tests\Support\Appointments\FakeServiceCatalog;
use Tests\Support\Appointments\FakeStaffDirectory;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;

beforeEach(function () {
    $this->journal = new AppointmentJournal;
    $this->appointments = new FakeAppointmentRepository($this->journal);

    $this->services = (new FakeServiceCatalog($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::serviceSnapshot());
    $this->customers = (new FakeCustomerDirectory($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::customerSnapshot());
    $this->staff = (new FakeStaffDirectory($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::staffSnapshot());

    $this->policies = Mockery::mock(CancellationPolicy::class);

    $this->allowCancelling = function (?CancellationRule $rule = null): void {
        $this->policies->shouldReceive('forBusiness')->andReturnUsing(function () use ($rule): CancellationRule {
            $this->journal->record('policies.forBusiness');

            return $rule ?? CancellationRule::ofMinutes(120);
        });
    };

    $this->dispatched = [];
    $this->savesBeforeDispatch = [];
    $this->events = Mockery::mock(Dispatcher::class);
    $this->events->shouldReceive('dispatch')->andReturnUsing(function (object $event): array {
        $this->dispatched[] = $event;
        $this->savesBeforeDispatch[] = count($this->appointments->saved);

        return [];
    });

    $this->build = fn (string $now = AppointmentFixtures::NOW): CancelGuestBooking => new CancelGuestBooking(
        $this->appointments,
        new GuestBookingFinder($this->appointments),
        $this->policies,
        new AppointmentChangeWindow,
        new GuestBookingPresenter($this->services, $this->customers, $this->staff),
        new FakeClock(AppointmentFixtures::instant($now)),
        $this->events,
    );

    $this->cancel = fn (string $now = AppointmentFixtures::NOW, ...$overrides): UseCaseResponse => ($this->build)($now)
        ->handle(AppointmentFixtures::cancelGuestInput(...$overrides));
});

describe('a guest cancelling their own booking', function () {
    beforeEach(function () {
        $this->appointments->store(AppointmentFixtures::guestAppointment());
        ($this->allowCancelling)();
    });

    it('answers with the cancelled booking, field by field', function () {
        $booking = ($this->cancel)()->value();

        expect($booking)->toBeInstanceOf(GuestBookingData::class)
            ->and($booking->referenceCode)->toBe(AppointmentFixtures::REFERENCE_CODE)
            ->and($booking->status)->toBe(AppointmentStatus::Cancelled)
            ->and($booking->cancelledAt)->toEqual(AppointmentFixtures::now())
            ->and($booking->changeable)->toBeFalse();
    });

    it('records the customer as the canceller, never the business', function () {
        ($this->cancel)();

        expect($this->appointments->saved[0]->cancelledBy())->toBe(Canceller::Customer);
    });

    it('stamps the cancellation with the injected clock', function () {
        ($this->cancel)('2026-03-09T10:00:00+00:00');

        expect($this->appointments->saved[0]->cancelledAt())
            ->toEqual(new DateTimeImmutable('2026-03-09T10:00:00+00:00'));
    });

    it('saves the cancelled booking exactly once', function () {
        ($this->cancel)();

        expect($this->appointments->saved)->toHaveCount(1);
    });

    it('hands back no manage token and no appointment uuid', function () {
        $serialized = json_encode(($this->cancel)()->value(), JSON_THROW_ON_ERROR);

        expect($serialized)->not->toContain(AppointmentFixtures::MANAGE_TOKEN)
            ->and($serialized)->not->toContain(AppointmentFixtures::APPOINTMENT_ID)
            ->and($serialized)->not->toContain(AppointmentFixtures::CUSTOMER_EMAIL);
    });

    it('announces the cancellation exactly once, carrying the appointment uuid', function () {
        ($this->cancel)();

        expect($this->dispatched)->toHaveCount(1)
            ->and($this->dispatched[0])->toBeInstanceOf(AppointmentCancelled::class)
            ->and($this->dispatched[0]->id)->toBe(AppointmentFixtures::APPOINTMENT_ID);
    });

    it('announces the cancellation only after the booking is saved', function () {
        ($this->cancel)();

        expect($this->savesBeforeDispatch)->toBe([1]);
    });
});

describe('the window consulted before the entity is touched', function () {
    beforeEach(function () {
        $this->appointments->store(AppointmentFixtures::guestAppointment());
    });

    it('refuses with appointment_changes_not_allowed when the business allows no cancellation', function () {
        ($this->allowCancelling)(CancellationRule::notAllowed());

        $response = ($this->cancel)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_changes_not_allowed')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden);
    });

    it('refuses with cancellation_window_closed when the window has run out', function () {
        ($this->allowCancelling)(CancellationRule::ofMinutes(120));

        $response = ($this->cancel)('2026-03-10T08:30:00+00:00');

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('cancellation_window_closed')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('keeps the two refusals distinct, so the guest is told which rule stopped them', function () {
        ($this->allowCancelling)(CancellationRule::notAllowed());
        $forbidden = ($this->cancel)()->error();

        $this->policies = Mockery::mock(CancellationPolicy::class);
        ($this->allowCancelling)(CancellationRule::ofMinutes(120));
        $closed = ($this->cancel)('2026-03-10T08:30:00+00:00')->error();

        expect($forbidden->code)->toBe('appointment_changes_not_allowed')
            ->and($closed->code)->toBe('cancellation_window_closed')
            ->and($forbidden->code)->not->toBe($closed->code)
            ->and($forbidden->kind)->not->toBe($closed->kind);
    });

    it('allows the cancellation at the exact minute the window still permits', function () {
        ($this->allowCancelling)(CancellationRule::ofMinutes(120));

        expect(($this->cancel)('2026-03-10T07:00:00+00:00')->succeeded())->toBeTrue();
    });

    it('refuses one minute after the window has closed', function () {
        ($this->allowCancelling)(CancellationRule::ofMinutes(120));

        expect(($this->cancel)('2026-03-10T07:00:01+00:00')->error()->code)
            ->toBe('cancellation_window_closed');
    });

    it('consults the policy before it touches the entity', function () {
        ($this->allowCancelling)(CancellationRule::notAllowed());

        ($this->cancel)();

        expect($this->journal->entries)->toBe([
            'appointments.findByReferenceCode',
            'policies.forBusiness',
        ]);
    });

    it('saves nothing when the window refuses', function (CancellationRule $rule, string $now) {
        ($this->allowCancelling)($rule);

        ($this->cancel)($now);

        expect($this->appointments->saved)->toBe([])
            ->and($this->journal->entries)->not->toContain('appointments.save');
    })->with([
        'cancellation forbidden' => [fn () => CancellationRule::notAllowed(), AppointmentFixtures::NOW],
        'window closed' => [fn () => CancellationRule::ofMinutes(120), '2026-03-10T08:30:00+00:00'],
    ]);

    it('announces nothing when the window refuses', function (CancellationRule $rule, string $now) {
        ($this->allowCancelling)($rule);

        ($this->cancel)($now);

        expect($this->dispatched)->toBe([]);
    })->with([
        'cancellation forbidden' => [fn () => CancellationRule::notAllowed(), AppointmentFixtures::NOW],
        'window closed' => [fn () => CancellationRule::ofMinutes(120), '2026-03-10T08:30:00+00:00'],
    ]);

    it('leaves the booking uncancelled when the window refuses', function () {
        ($this->allowCancelling)(CancellationRule::notAllowed());

        ($this->cancel)();

        expect($this->appointments->findByReferenceCode(
            FakeBusinessContext::BUSINESS_ID,
            AppointmentFixtures::REFERENCE_CODE,
        )?->isCancelled())->toBeFalse();
    });

    it('asks the policy about the business whose page the link belongs to', function () {
        $asked = null;

        $this->policies->shouldReceive('forBusiness')->once()
            ->with(Mockery::capture($asked))
            ->andReturn(CancellationRule::ofMinutes(120));

        ($this->cancel)();

        expect($asked)->toBe(FakeBusinessContext::BUSINESS_ID);
    });
});

describe('credentials that do not open a booking', function () {
    beforeEach(function () {
        $this->appointments->store(AppointmentFixtures::guestAppointment());
        ($this->allowCancelling)();

        $this->refusalFor = fn (string $now, ...$overrides): array => [
            'code' => ($this->cancel)($now, ...$overrides)->error()->code,
            'kind' => ($this->cancel)($now, ...$overrides)->error()->kind,
            'message' => ($this->cancel)($now, ...$overrides)->error()->cause()?->getMessage(),
        ];
    });

    it('tells an unknown code, a wrong token and an expired token apart in no way at all', function () {
        $unknownCode = ($this->refusalFor)(AppointmentFixtures::NOW, credentials: AppointmentFixtures::credentials(
            referenceCode: AppointmentFixtures::UNKNOWN_REFERENCE_CODE,
        ));
        $wrongToken = ($this->refusalFor)(AppointmentFixtures::NOW, credentials: AppointmentFixtures::credentials(
            manageToken: AppointmentFixtures::OTHER_MANAGE_TOKEN,
        ));
        $expiredToken = ($this->refusalFor)('2026-03-18T09:00:00+00:00');

        expect($unknownCode)->toBe($wrongToken)
            ->and($wrongToken)->toBe($expiredToken)
            ->and($unknownCode['code'])->toBe('guest_booking_not_found')
            ->and($unknownCode['kind'])->toBe(DomainFailureKind::NotFound);
    });

    it('never consults the policy for credentials that opened nothing', function () {
        ($this->cancel)(AppointmentFixtures::NOW, credentials: AppointmentFixtures::credentials(
            referenceCode: AppointmentFixtures::UNKNOWN_REFERENCE_CODE,
        ));

        expect($this->journal->entries)->toBe(['appointments.findByReferenceCode'])
            ->and($this->journal->entries)->not->toContain('policies.forBusiness');
    });

    it('refuses a booking of a neighbouring business holding the very same code', function () {
        expect(($this->cancel)(AppointmentFixtures::NOW, businessId: AppointmentFixtures::OTHER_BUSINESS_ID)
            ->error()->code)->toBe('guest_booking_not_found');
    });

    it('saves nothing when the credentials do not open a booking', function () {
        ($this->cancel)(AppointmentFixtures::NOW, credentials: AppointmentFixtures::credentials(
            manageToken: AppointmentFixtures::OTHER_MANAGE_TOKEN,
        ));

        expect($this->appointments->saved)->toBe([]);
    });

    it('announces nothing when the credentials do not open a booking', function () {
        ($this->cancel)(AppointmentFixtures::NOW, credentials: AppointmentFixtures::credentials(
            manageToken: AppointmentFixtures::OTHER_MANAGE_TOKEN,
        ));

        expect($this->dispatched)->toBe([]);
    });
});

describe('a booking the entity itself will not cancel', function () {
    it('refuses one that is already cancelled', function () {
        $cancelled = AppointmentFixtures::guestAppointment();
        $cancelled->cancel(Canceller::Customer, AppointmentFixtures::now());

        $this->appointments->store($cancelled);
        ($this->allowCancelling)();

        $response = ($this->cancel)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_already_cancelled')
            ->and($this->appointments->saved)->toBe([]);
    });
});
