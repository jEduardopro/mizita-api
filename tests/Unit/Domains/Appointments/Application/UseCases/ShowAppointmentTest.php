<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\Dtos\AppointmentData;
use App\Domains\Appointments\Application\Presenters\AppointmentPresenter;
use App\Domains\Appointments\Application\UseCases\ShowAppointment;
use App\Domains\Appointments\ValueObjects\AppointmentStatus;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Appointments\AppointmentFixtures;
use Tests\Support\Appointments\AppointmentJournal;
use Tests\Support\Appointments\FakeAppointmentRepository;
use Tests\Support\Appointments\FakeCalendarAccess;
use Tests\Support\Appointments\FakeCustomerDirectory;
use Tests\Support\Appointments\FakePaymentLedger;
use Tests\Support\Appointments\FakeServiceCatalog;
use Tests\Support\Appointments\FakeStaffDirectory;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->journal = new AppointmentJournal;
    $this->appointments = new FakeAppointmentRepository($this->journal);

    $this->services = (new FakeServiceCatalog($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::serviceSnapshot());
    $this->customers = (new FakeCustomerDirectory($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::customerSnapshot());
    $this->staff = (new FakeStaffDirectory($this->journal))
        ->add(
            FakeBusinessContext::BUSINESS_ID,
            AppointmentFixtures::staffSnapshot(),
            AppointmentFixtures::staffSnapshot(
                id: AppointmentFixtures::SECOND_STAFF_ID,
                name: AppointmentFixtures::SECOND_STAFF_NAME,
            ),
        );
    $this->payments = new FakePaymentLedger($this->journal);
    $this->calendars = FakeCalendarAccess::everyone();

    $this->appointments->store(
        AppointmentFixtures::appointment(),
        AppointmentFixtures::appointment(
            id: AppointmentFixtures::SECOND_APPOINTMENT_ID,
            staffMemberId: AppointmentFixtures::SECOND_STAFF_ID,
        ),
        AppointmentFixtures::appointment(
            id: AppointmentFixtures::FOREIGN_APPOINTMENT_ID,
            businessId: AppointmentFixtures::OTHER_BUSINESS_ID,
        ),
    );

    $this->show = fn (string $appointmentId = AppointmentFixtures::APPOINTMENT_ID, string $accountId = AppointmentFixtures::ACCOUNT_ID) => (new ShowAppointment(
        $this->appointments,
        new AppointmentPresenter($this->services, $this->customers, $this->staff, $this->payments),
        new FakeBusinessContext,
        $this->calendars,
    ))->handle(AppointmentFixtures::showInput($appointmentId, $accountId));
});

describe('showing an appointment of the business', function () {
    it('answers with every field the client reads', function () {
        $data = ($this->show)()->value();

        expect($data)->toBeInstanceOf(AppointmentData::class)
            ->and($data->id)->toBe(AppointmentFixtures::APPOINTMENT_ID)
            ->and($data->status)->toBe(AppointmentStatus::Booked)
            ->and($data->customer->id)->toBe(AppointmentFixtures::CUSTOMER_ID)
            ->and($data->customer->name)->toBe(AppointmentFixtures::CUSTOMER_NAME)
            ->and($data->service->id)->toBe(AppointmentFixtures::SERVICE_ID)
            ->and($data->service->name)->toBe(AppointmentFixtures::SERVICE_NAME)
            ->and($data->staffMember->id)->toBe(AppointmentFixtures::STAFF_ID)
            ->and($data->staffMember->name)->toBe(AppointmentFixtures::STAFF_NAME)
            ->and($data->startsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::STARTS_AT))
            ->and($data->endsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::ENDS_AT))
            ->and($data->notes)->toBe(AppointmentFixtures::NOTES)
            ->and($data->createdAt)->toEqual(AppointmentFixtures::now());
    });

    it('hands back uuids, never an internal key', function () {
        $data = ($this->show)()->value();

        expect(is_numeric($data->id))->toBeFalse()
            ->and(is_numeric($data->customer->id))->toBeFalse()
            ->and(is_numeric($data->service->id))->toBeFalse()
            ->and(is_numeric($data->staffMember->id))->toBeFalse();
    });

    it('reads only the business in context', function () {
        ($this->show)();

        expect(array_unique($this->appointments->businessIdsSeen))->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('shows a caller who keeps every calendar the appointment of any team member', function () {
        expect(($this->show)(AppointmentFixtures::SECOND_APPOINTMENT_ID)->value()->staffMember->id)
            ->toBe(AppointmentFixtures::SECOND_STAFF_ID);
    });
});

describe('an appointment the caller may not reach', function () {
    it('answers not found when no appointment carries that uuid', function () {
        $response = ($this->show)(AppointmentFixtures::UNKNOWN_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('answers not found for an appointment of another business', function () {
        expect(($this->show)(AppointmentFixtures::FOREIGN_APPOINTMENT_ID)->error()->code)
            ->toBe('appointment_not_found');
    });

    it('refuses a malformed uuid before it reads anything', function (string $appointmentId) {
        $response = ($this->show)($appointmentId);

        expect($response->error()->code)->toBe('appointment_not_found')
            ->and($this->journal->entries)->toBe([])
            ->and($this->calendars->lookups)->toBe([]);
    })->with([
        'empty' => '',
        'a word' => 'not-a-uuid',
        'an integer key' => '7',
    ]);
});

describe('a caller who keeps only their own calendar', function () {
    beforeEach(function () {
        $this->calendars = FakeCalendarAccess::ownedBy(AppointmentFixtures::STAFF_ID);
    });

    it('shows an appointment on their own calendar', function () {
        expect(($this->show)()->value()->id)->toBe(AppointmentFixtures::APPOINTMENT_ID);
    });

    it('answers not found for an appointment on the calendar of another team member', function () {
        $response = ($this->show)(AppointmentFixtures::SECOND_APPOINTMENT_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('describes nothing of an appointment it refused to show', function () {
        ($this->show)(AppointmentFixtures::SECOND_APPOINTMENT_ID);

        expect($this->journal->entries)->toBe(['appointments.findWithinScope']);
    });

    it('looks the appointment up within the scope the caller was granted', function () {
        ($this->show)();

        expect($this->appointments->scopesSeen[0]->restrictedStaffMemberId())->toBe(AppointmentFixtures::STAFF_ID);
    });
});

describe('the calendar the caller is allowed to keep', function () {
    it('asks for the scope of the account on the input, in the business in context', function () {
        ($this->show)(AppointmentFixtures::APPOINTMENT_ID, AppointmentFixtures::SECOND_ACCOUNT_ID);

        expect($this->calendars->lookups)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'accountId' => AppointmentFixtures::SECOND_ACCOUNT_ID,
        ]]);
    });

    it('refuses an account that is no member of the business before it reads anything', function () {
        $this->calendars = FakeCalendarAccess::refusing();

        $response = ($this->show)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_accessible')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden)
            ->and($this->journal->entries)->toBe([]);
    });

    it('refuses a malformed account before it asks for any scope', function () {
        $response = ($this->show)(AppointmentFixtures::APPOINTMENT_ID, 'not-a-uuid');

        expect($response->error()->code)->toBe('business_not_accessible')
            ->and($this->calendars->lookups)->toBe([])
            ->and($this->journal->entries)->toBe([]);
    });
});
