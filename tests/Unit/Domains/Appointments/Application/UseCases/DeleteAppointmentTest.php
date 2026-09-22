<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\UseCases\DeleteAppointment;
use App\Domains\Appointments\ValueObjects\AppointmentPaymentStatus;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Appointments\AppointmentFixtures;
use Tests\Support\Appointments\AppointmentJournal;
use Tests\Support\Appointments\FakeAppointmentRepository;
use Tests\Support\Appointments\FakePaymentLedger;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->journal = new AppointmentJournal;
    $this->appointments = new FakeAppointmentRepository($this->journal);
    $this->payments = new FakePaymentLedger($this->journal);

    $this->build = fn (string $businessId = FakeBusinessContext::BUSINESS_ID): DeleteAppointment => new DeleteAppointment(
        $this->appointments,
        $this->payments,
        new FakeBusinessContext($businessId),
    );

    $this->delete = fn (?string $appointmentId = null, string $businessId = FakeBusinessContext::BUSINESS_ID) => (
        ($this->build)($businessId)
    )->handle(AppointmentFixtures::deleteInput($appointmentId ?? AppointmentFixtures::APPOINTMENT_ID));
});

describe('deleting an appointment nobody has paid for', function () {
    beforeEach(function () {
        $this->appointments->store(AppointmentFixtures::appointment());
    });

    it('answers with a success carrying no data', function () {
        $response = ($this->delete)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull();
    });

    it('asks the repository to delete that appointment exactly once', function () {
        ($this->delete)();

        expect($this->appointments->deleted)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'id' => AppointmentFixtures::APPOINTMENT_ID,
        ]]);
    });

    it('checks the ledger before it deletes anything', function () {
        ($this->delete)();

        expect($this->journal->entries)->toBe(['payments.hasPaymentFor', 'appointments.delete']);
    });

    it('scopes both the ledger check and the delete to the business the caller operates in', function () {
        ($this->delete)();

        expect($this->payments->paymentChecks)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'appointmentId' => AppointmentFixtures::APPOINTMENT_ID,
        ]])->and($this->appointments->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('takes the business from the context, never from the payload', function () {
        $this->appointments->store(AppointmentFixtures::appointment(
            businessId: AppointmentFixtures::OTHER_BUSINESS_ID,
        ));

        ($this->delete)(businessId: AppointmentFixtures::OTHER_BUSINESS_ID);

        expect($this->payments->paymentChecks[0]['businessId'])->toBe(AppointmentFixtures::OTHER_BUSINESS_ID)
            ->and($this->appointments->deleted[0]['businessId'])->toBe(AppointmentFixtures::OTHER_BUSINESS_ID);
    });
});

describe('deleting an appointment that has been charged', function () {
    beforeEach(function () {
        $this->appointments->store(AppointmentFixtures::appointment());
        $this->payments->add(
            FakeBusinessContext::BUSINESS_ID,
            AppointmentFixtures::APPOINTMENT_ID,
            AppointmentFixtures::paymentSnapshot(),
        );
    });

    it('refuses with a conflict the client can act on', function () {
        $response = ($this->delete)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_has_payment')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('deletes nothing at all', function () {
        ($this->delete)();

        expect($this->appointments->deleted)->toBe([])
            ->and($this->journal->entries)->not->toContain('appointments.delete');
    });

    it('leaves the appointment where the next read can still find it', function () {
        ($this->delete)();

        expect($this->appointments->findForBusiness(
            FakeBusinessContext::BUSINESS_ID,
            AppointmentFixtures::APPOINTMENT_ID,
        )->id)->toBe(AppointmentFixtures::APPOINTMENT_ID);
    });

    it('refuses whatever the payment has settled so far', function (AppointmentPaymentStatus $status) {
        $this->payments->add(
            FakeBusinessContext::BUSINESS_ID,
            AppointmentFixtures::APPOINTMENT_ID,
            AppointmentFixtures::paymentSnapshot(status: $status),
        );

        expect(($this->delete)()->error()->code)->toBe('appointment_has_payment');
    })->with([
        'pending' => AppointmentPaymentStatus::Pending,
        'partially paid' => AppointmentPaymentStatus::PartiallyPaid,
        'paid' => AppointmentPaymentStatus::Paid,
    ]);
});

describe('the payments of a business the caller is not in', function () {
    it('deletes an appointment whose only payment belongs to another business', function () {
        $this->appointments->store(AppointmentFixtures::appointment());
        $this->payments->add(
            AppointmentFixtures::OTHER_BUSINESS_ID,
            AppointmentFixtures::APPOINTMENT_ID,
            AppointmentFixtures::paymentSnapshot(),
        );

        $response = ($this->delete)();

        expect($response->succeeded())->toBeTrue()
            ->and($this->appointments->deleted)->toHaveCount(1);
    });
});

describe('an appointment the caller may not reach', function () {
    it('refuses a malformed uuid before it reads or writes anything', function () {
        $response = ($this->delete)('not-a-uuid');

        expect($response->error()->code)->toBe('appointment_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->journal->entries)->toBe([]);
    });

    it('refuses an empty appointment id before it reads or writes anything', function () {
        expect(($this->delete)('')->error()->code)->toBe('appointment_not_found')
            ->and($this->journal->entries)->toBe([]);
    });

    it('answers with a refusal when no appointment carries that uuid', function () {
        $response = ($this->delete)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('answers with a refusal for an appointment of another business', function () {
        $this->appointments->store(AppointmentFixtures::appointment(
            businessId: AppointmentFixtures::OTHER_BUSINESS_ID,
        ));

        expect(($this->delete)()->error()->code)->toBe('appointment_not_found')
            ->and($this->appointments->deleted)->toBe([]);
    });
});
