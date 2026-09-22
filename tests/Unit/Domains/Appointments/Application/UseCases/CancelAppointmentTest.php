<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\Dtos\AppointmentData;
use App\Domains\Appointments\Application\Presenters\AppointmentPresenter;
use App\Domains\Appointments\Application\UseCases\CancelAppointment;
use App\Domains\Appointments\Contracts\CancellationPolicy;
use App\Domains\Appointments\Services\AppointmentChangeWindow;
use App\Domains\Appointments\ValueObjects\AppointmentPaymentStatus;
use App\Domains\Appointments\ValueObjects\AppointmentStatus;
use App\Domains\Appointments\ValueObjects\Canceller;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Appointments\AppointmentFixtures;
use Tests\Support\Appointments\AppointmentJournal;
use Tests\Support\Appointments\FakeAppointmentRepository;
use Tests\Support\Appointments\FakeCustomerDirectory;
use Tests\Support\Appointments\FakePaymentLedger;
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

    $this->payments = new FakePaymentLedger($this->journal);

    $this->build = fn (string $now = AppointmentFixtures::NOW): CancelAppointment => new CancelAppointment(
        $this->appointments,
        new AppointmentPresenter($this->services, $this->customers, $this->staff, $this->payments),
        new FakeBusinessContext,
        new FakeClock(AppointmentFixtures::instant($now)),
    );

    $this->cancel = fn (string $now = AppointmentFixtures::NOW, ?string $appointmentId = null) => ($this->build)($now)
        ->handle(AppointmentFixtures::cancelInput($appointmentId ?? AppointmentFixtures::APPOINTMENT_ID));
});

describe('the business cancelling an appointment', function () {
    beforeEach(function () {
        $this->appointments->store(AppointmentFixtures::appointment());
    });

    it('answers with the cancelled appointment, field by field', function () {
        $data = ($this->cancel)()->value();

        expect($data)->toBeInstanceOf(AppointmentData::class)
            ->and($data->id)->toBe(AppointmentFixtures::APPOINTMENT_ID)
            ->and($data->status)->toBe(AppointmentStatus::Cancelled)
            ->and($data->cancelledAt)->toEqual(AppointmentFixtures::now())
            ->and($data->cancelledBy)->toBe(Canceller::Business);
    });

    it('records the business as the canceller, never the customer', function () {
        ($this->cancel)();

        expect($this->appointments->saved[0]->cancelledBy())->toBe(Canceller::Business);
    });

    it('stamps the cancellation with the injected clock', function () {
        ($this->cancel)('2026-02-02T08:30:00+00:00');

        expect($this->appointments->saved[0]->cancelledAt())
            ->toEqual(new DateTimeImmutable('2026-02-02T08:30:00+00:00'));
    });

    it('saves the cancelled appointment exactly once', function () {
        ($this->cancel)();

        expect($this->appointments->saved)->toHaveCount(1);
    });

    it('scopes the read and the save to the business the caller operates in', function () {
        ($this->cancel)();

        expect(array_unique($this->appointments->businessIdsSeen))->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('hands back the appointment uuid, never an internal key', function () {
        $data = ($this->cancel)()->value();

        expect($data->id)->toBeString()
            ->and(is_numeric($data->id))->toBeFalse();
    });
});

describe('the window the business is not held to', function () {
    it('takes no cancellation policy and no change window among its collaborators', function () {
        $types = array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            (new ReflectionMethod(CancelAppointment::class, '__construct'))->getParameters(),
        );

        expect($types)->not->toContain(CancellationPolicy::class)
            ->and($types)->not->toContain(AppointmentChangeWindow::class);
    });

    it('cancels an appointment starting in one minute, which a customer could not', function () {
        $this->appointments->store(AppointmentFixtures::appointment());

        $response = ($this->cancel)('2026-03-10T08:59:00+00:00');

        expect($response->succeeded())->toBeTrue()
            ->and($response->value()->status)->toBe(AppointmentStatus::Cancelled);
    });

    it('cancels an appointment months out just as readily', function () {
        $this->appointments->store(AppointmentFixtures::appointment());

        expect(($this->cancel)('2026-01-01T00:00:00+00:00')->succeeded())->toBeTrue();
    });

    it('cancels a booking the customer made from the public page', function () {
        $this->appointments->store(AppointmentFixtures::guestAppointment());

        $response = ($this->cancel)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value()->cancelledBy)->toBe(Canceller::Business);
    });
});

describe('the payment a cancellation is not held back by', function () {
    beforeEach(function () {
        $this->appointments->store(AppointmentFixtures::appointment());
        $this->payments->add(
            FakeBusinessContext::BUSINESS_ID,
            AppointmentFixtures::APPOINTMENT_ID,
            AppointmentFixtures::paymentSnapshot(),
        );
    });

    it('cancels an appointment that has been paid in full, which deleting it would refuse', function () {
        $response = ($this->cancel)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value()->status)->toBe(AppointmentStatus::Cancelled);
    });

    it('cancels whatever the payment has settled so far', function (AppointmentPaymentStatus $status) {
        $this->payments->add(
            FakeBusinessContext::BUSINESS_ID,
            AppointmentFixtures::APPOINTMENT_ID,
            AppointmentFixtures::paymentSnapshot(status: $status),
        );

        expect(($this->cancel)()->succeeded())->toBeTrue();
    })->with([
        'pending' => AppointmentPaymentStatus::Pending,
        'partially paid' => AppointmentPaymentStatus::PartiallyPaid,
        'paid' => AppointmentPaymentStatus::Paid,
    ]);

    it('hands the payment status back on the cancelled appointment', function () {
        expect(($this->cancel)()->value()->paymentStatus)->toBe(AppointmentPaymentStatus::Paid);
    });

    it('never asks the ledger whether deletion would be allowed', function () {
        ($this->cancel)();

        expect($this->journal->entries)->not->toContain('payments.hasPaymentFor');
    });
});

describe('the guards the entity still applies', function () {
    it('refuses an appointment that is already cancelled', function () {
        $this->appointments->store(AppointmentFixtures::appointment(
            cancelledAt: '2026-01-02T10:00:00+00:00',
            cancelledBy: Canceller::Customer,
        ));

        $response = ($this->cancel)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_already_cancelled')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('refuses an appointment that has already started', function () {
        $this->appointments->store(AppointmentFixtures::appointment());

        $response = ($this->cancel)('2026-03-10T09:30:00+00:00');

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_already_started')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('refuses at the exact instant the appointment starts', function () {
        $this->appointments->store(AppointmentFixtures::appointment());

        expect(($this->cancel)(AppointmentFixtures::STARTS_AT)->error()->code)
            ->toBe('appointment_already_started');
    });

    it('saves nothing when a guard refuses', function (string $now, string $expectedCode) {
        $this->appointments->store(AppointmentFixtures::appointment(
            cancelledAt: $expectedCode === 'appointment_already_cancelled' ? '2026-01-02T10:00:00+00:00' : null,
            cancelledBy: $expectedCode === 'appointment_already_cancelled' ? Canceller::Customer : null,
        ));

        $response = ($this->cancel)($now);

        expect($response->error()->code)->toBe($expectedCode)
            ->and($this->appointments->saved)->toBe([])
            ->and($this->journal->entries)->not->toContain('appointments.save');
    })->with([
        'already cancelled' => [AppointmentFixtures::NOW, 'appointment_already_cancelled'],
        'already started' => ['2026-03-10T09:30:00+00:00', 'appointment_already_started'],
    ]);
});

describe('an appointment the caller may not reach', function () {
    it('answers with a refusal when no appointment carries that uuid', function () {
        $response = ($this->cancel)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('answers with a refusal for an appointment of another business', function () {
        $this->appointments->store(AppointmentFixtures::appointment(
            businessId: AppointmentFixtures::OTHER_BUSINESS_ID,
        ));

        expect(($this->cancel)()->error()->code)->toBe('appointment_not_found')
            ->and($this->appointments->saved)->toBe([]);
    });

    it('refuses a malformed uuid before it reads anything', function () {
        $response = ($this->cancel)(AppointmentFixtures::NOW, 'not-a-uuid');

        expect($response->error()->code)->toBe('appointment_not_found')
            ->and($this->journal->entries)->toBe([]);
    });
});
