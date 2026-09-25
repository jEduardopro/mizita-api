<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\Dtos\AppointmentData;
use App\Domains\Appointments\Application\Presenters\AppointmentPresenter;
use App\Domains\Appointments\Application\UseCases\ListAppointments;
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
            startsAt: '2026-03-12T09:00:00+00:00',
            endsAt: '2026-03-12T10:00:00+00:00',
        ),
        AppointmentFixtures::appointment(
            id: AppointmentFixtures::THIRD_APPOINTMENT_ID,
            startsAt: '2026-04-10T09:00:00+00:00',
            endsAt: '2026-04-10T10:00:00+00:00',
        ),
        AppointmentFixtures::appointment(
            id: AppointmentFixtures::FOREIGN_APPOINTMENT_ID,
            businessId: AppointmentFixtures::OTHER_BUSINESS_ID,
        ),
    );

    $this->list = fn (...$overrides) => (new ListAppointments(
        $this->appointments,
        new AppointmentPresenter($this->services, $this->customers, $this->staff, $this->payments),
        new FakeBusinessContext,
        $this->calendars,
    ))->handle(AppointmentFixtures::listInput(...$overrides));

    $this->idsOf = static fn (array $items): array => array_map(
        static fn (AppointmentData $data): string => $data->id,
        $items,
    );
});

describe('the calendar of the business', function () {
    it('answers with every appointment of the business in the range', function () {
        expect(($this->idsOf)(($this->list)()->value()))->toEqualCanonicalizing([
            AppointmentFixtures::APPOINTMENT_ID,
            AppointmentFixtures::SECOND_APPOINTMENT_ID,
        ]);
    });

    it('describes each row field by field', function () {
        $rows = ($this->list)()->value();
        $data = array_values(array_filter(
            $rows,
            static fn (AppointmentData $row): bool => $row->id === AppointmentFixtures::APPOINTMENT_ID,
        ))[0];

        expect($data)->toBeInstanceOf(AppointmentData::class)
            ->and($data->customer->id)->toBe(AppointmentFixtures::CUSTOMER_ID)
            ->and($data->service->id)->toBe(AppointmentFixtures::SERVICE_ID)
            ->and($data->staffMember->id)->toBe(AppointmentFixtures::STAFF_ID)
            ->and($data->staffMember->name)->toBe(AppointmentFixtures::STAFF_NAME)
            ->and($data->startsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::STARTS_AT))
            ->and($data->endsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::ENDS_AT));
    });

    it('shows nothing of the appointments of another business', function () {
        expect(($this->idsOf)(($this->list)()->value()))->not->toContain(AppointmentFixtures::FOREIGN_APPOINTMENT_ID)
            ->and(array_unique($this->appointments->businessIdsSeen))->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('hands the repository the range the input built', function () {
        ($this->list)();

        expect($this->appointments->searches)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'from' => AppointmentFixtures::RANGE_FROM,
            'to' => AppointmentFixtures::RANGE_TO,
        ]]);
    });

    it('answers an empty range with a success, not a refusal', function () {
        $response = ($this->list)(from: '2026-06-01T00:00:00+00:00', to: '2026-06-30T00:00:00+00:00');

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBe([]);
    });

    it('refuses a range it cannot read before it asks for any scope', function () {
        $response = ($this->list)(from: '2026-03-31T00:00:00+00:00', to: '2026-03-01T00:00:00+00:00');

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_calendar_range')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->calendars->lookups)->toBe([])
            ->and($this->appointments->searches)->toBe([]);
    });
});

describe('a caller who keeps only their own calendar', function () {
    beforeEach(function () {
        $this->calendars = FakeCalendarAccess::ownedBy(AppointmentFixtures::STAFF_ID);
    });

    it('answers with the appointments on their own calendar only', function () {
        expect(($this->idsOf)(($this->list)()->value()))->toBe([AppointmentFixtures::APPOINTMENT_ID]);
    });

    it('leaves out every appointment booked with another team member', function () {
        $staffIds = array_map(
            static fn (AppointmentData $data): string => $data->staffMember->id,
            ($this->list)()->value(),
        );

        expect($staffIds)->not->toContain(AppointmentFixtures::SECOND_STAFF_ID)
            ->and(($this->idsOf)(($this->list)()->value()))->not->toContain(AppointmentFixtures::SECOND_APPOINTMENT_ID);
    });

    it('filters silently rather than refusing the calendar', function () {
        expect(($this->list)()->succeeded())->toBeTrue();
    });

    it('hands the repository the scope the caller was granted', function () {
        ($this->list)();

        expect($this->appointments->scopesSeen)->toHaveCount(1)
            ->and($this->appointments->scopesSeen[0]->restrictedStaffMemberId())->toBe(AppointmentFixtures::STAFF_ID);
    });

    it('answers an empty calendar to a team member with nothing booked', function () {
        $this->calendars = FakeCalendarAccess::ownedBy(AppointmentFixtures::UNKNOWN_ID);

        expect(($this->list)()->value())->toBe([]);
    });
});

describe('the calendar the caller is allowed to keep', function () {
    it('asks for the scope of the account on the input, in the business in context', function () {
        ($this->list)(accountId: AppointmentFixtures::SECOND_ACCOUNT_ID);

        expect($this->calendars->lookups)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'accountId' => AppointmentFixtures::SECOND_ACCOUNT_ID,
        ]]);
    });

    it('hands the repository an unrestricted scope for a caller who keeps every calendar', function () {
        ($this->list)();

        expect($this->appointments->scopesSeen[0]->restrictedStaffMemberId())->toBeNull();
    });

    it('refuses an account that is no member of the business and searches nothing', function () {
        $this->calendars = FakeCalendarAccess::refusing();

        $response = ($this->list)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_accessible')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden)
            ->and($this->appointments->searches)->toBe([]);
    });

    it('refuses a malformed account before it asks for any scope', function () {
        $response = ($this->list)(accountId: 'not-a-uuid');

        expect($response->error()->code)->toBe('business_not_accessible')
            ->and($this->calendars->lookups)->toBe([])
            ->and($this->journal->entries)->toBe([]);
    });
});
