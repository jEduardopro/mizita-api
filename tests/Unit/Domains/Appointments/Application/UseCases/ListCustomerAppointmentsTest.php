<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\Dtos\AppointmentData;
use App\Domains\Appointments\Application\Presenters\AppointmentPresenter;
use App\Domains\Appointments\Application\UseCases\ListCustomerAppointments;
use App\Domains\Appointments\ValueObjects\AppointmentStatus;
use App\Domains\Appointments\ValueObjects\Canceller;
use App\Shared\ValueObjects\DomainFailureKind;
use App\Shared\ValueObjects\Paginated;
use App\Shared\ValueObjects\Pagination;
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
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::customerSnapshot(
            phone: AppointmentFixtures::customerPhoneSnapshot(),
        ));
    $this->staff = (new FakeStaffDirectory($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::staffSnapshot());

    $this->payments = new FakePaymentLedger($this->journal);
    $this->calendars = FakeCalendarAccess::everyone();

    $this->build = fn (?FakeBusinessContext $business = null): ListCustomerAppointments => new ListCustomerAppointments(
        $this->appointments,
        $this->customers,
        new AppointmentPresenter($this->services, $this->customers, $this->staff, $this->payments),
        $business ?? new FakeBusinessContext,
        $this->calendars,
    );

    $this->list = fn (?int $page = null, ?int $perPage = null, ?string $customerId = null) => ($this->build)()
        ->handle(AppointmentFixtures::listCustomerInput(
            customerId: $customerId ?? AppointmentFixtures::CUSTOMER_ID,
            page: $page,
            perPage: $perPage,
        ));
});

describe('the appointments it answers with', function () {
    it('answers with the appointments of that customer, newest first', function () {
        $this->appointments->store(
            AppointmentFixtures::appointment(
                id: AppointmentFixtures::APPOINTMENT_ID,
                startsAt: '2026-03-10T09:00:00+00:00',
                endsAt: '2026-03-10T10:00:00+00:00',
            ),
            AppointmentFixtures::appointment(
                id: AppointmentFixtures::SECOND_APPOINTMENT_ID,
                startsAt: '2026-05-20T09:00:00+00:00',
                endsAt: '2026-05-20T10:00:00+00:00',
            ),
            AppointmentFixtures::appointment(
                id: AppointmentFixtures::THIRD_APPOINTMENT_ID,
                startsAt: '2026-04-15T09:00:00+00:00',
                endsAt: '2026-04-15T10:00:00+00:00',
            ),
        );

        $page = ($this->list)()->value();

        expect(array_map(static fn (AppointmentData $data): string => $data->id, $page->items))->toBe([
            AppointmentFixtures::SECOND_APPOINTMENT_ID,
            AppointmentFixtures::THIRD_APPOINTMENT_ID,
            AppointmentFixtures::APPOINTMENT_ID,
        ]);
    });

    it('describes each row field by field', function () {
        $this->appointments->store(AppointmentFixtures::appointment());

        $page = ($this->list)()->value();
        $data = $page->items[0];

        expect($page)->toBeInstanceOf(Paginated::class)
            ->and($data)->toBeInstanceOf(AppointmentData::class)
            ->and($data->id)->toBe(AppointmentFixtures::APPOINTMENT_ID)
            ->and($data->status)->toBe(AppointmentStatus::Booked)
            ->and($data->startsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::STARTS_AT))
            ->and($data->endsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::ENDS_AT))
            ->and($data->notes)->toBe(AppointmentFixtures::NOTES)
            ->and($data->customer->id)->toBe(AppointmentFixtures::CUSTOMER_ID)
            ->and($data->customer->name)->toBe(AppointmentFixtures::CUSTOMER_NAME)
            ->and($data->customer->phone?->countryCode)->toBe(AppointmentFixtures::CUSTOMER_PHONE_COUNTRY_CODE)
            ->and($data->customer->phone?->nationalNumber)->toBe(AppointmentFixtures::CUSTOMER_PHONE_NATIONAL_NUMBER)
            ->and($data->service->id)->toBe(AppointmentFixtures::SERVICE_ID)
            ->and($data->service->durationMinutes)->toBe(AppointmentFixtures::SERVICE_DURATION_MINUTES)
            ->and($data->service->bufferMinutes)->toBe(AppointmentFixtures::SERVICE_BUFFER_MINUTES)
            ->and($data->service->price)->toBe(AppointmentFixtures::SERVICE_PRICE)
            ->and($data->staffMember->id)->toBe(AppointmentFixtures::STAFF_ID);
    });

    it('hands back uuids, never an internal key', function () {
        $this->appointments->store(AppointmentFixtures::appointment());

        $data = ($this->list)()->value()->items[0];

        expect(is_numeric($data->id))->toBeFalse()
            ->and(is_numeric($data->customer->id))->toBeFalse()
            ->and(is_numeric($data->service->id))->toBeFalse()
            ->and(is_numeric($data->staffMember->id))->toBeFalse();
    });

    it('leaves out an appointment the customer cancelled', function () {
        $this->appointments->store(
            AppointmentFixtures::appointment(),
            AppointmentFixtures::appointment(
                id: AppointmentFixtures::SECOND_APPOINTMENT_ID,
                cancelledAt: '2026-02-01T10:00:00+00:00',
                cancelledBy: Canceller::Customer,
            ),
        );

        $page = ($this->list)()->value();

        expect($page->items)->toHaveCount(1)
            ->and($page->total)->toBe(1)
            ->and($page->items[0]->id)->toBe(AppointmentFixtures::APPOINTMENT_ID);
    });

    it('leaves out an appointment booked by somebody else', function () {
        $this->customers->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::customerSnapshot(
            id: AppointmentFixtures::SECOND_CUSTOMER_ID,
            name: AppointmentFixtures::SECOND_CUSTOMER_NAME,
        ));

        $this->appointments->store(
            AppointmentFixtures::appointment(),
            AppointmentFixtures::appointment(
                id: AppointmentFixtures::SECOND_APPOINTMENT_ID,
                customerId: AppointmentFixtures::SECOND_CUSTOMER_ID,
            ),
        );

        $page = ($this->list)()->value();

        expect($page->items)->toHaveCount(1)
            ->and($page->items[0]->customer->id)->toBe(AppointmentFixtures::CUSTOMER_ID);
    });

    it('answers an empty history with a success, not a refusal', function () {
        $response = ($this->list)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value()->items)->toBe([])
            ->and($response->value()->total)->toBe(0)
            ->and($response->value()->lastPage())->toBe(1);
    });
});

describe('the page it hands back', function () {
    beforeEach(function () {
        $this->storeAppointments = function (int $count): void {
            for ($index = 0; $index < $count; $index++) {
                $day = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);

                $this->appointments->store(AppointmentFixtures::appointment(
                    id: sprintf('01930000-0000-7000-8000-0000000001%02d', $index),
                    startsAt: "2026-03-{$day}T09:00:00+00:00",
                    endsAt: "2026-03-{$day}T10:00:00+00:00",
                ));
            }
        };
    });

    it('counts every appointment the customer has, not the rows on this page', function () {
        ($this->storeAppointments)(7);

        $page = ($this->list)(page: 1, perPage: 3)->value();

        expect($page->items)->toHaveCount(3)
            ->and($page->total)->toBe(7)
            ->and($page->lastPage())->toBe(3);
    });

    it('answers the second page with the rows that follow the first', function () {
        ($this->storeAppointments)(7);

        $first = ($this->list)(page: 1, perPage: 3)->value();
        $second = ($this->list)(page: 2, perPage: 3)->value();

        $idsOf = static fn (array $items): array => array_map(
            static fn (AppointmentData $data): string => $data->id,
            $items,
        );

        expect($second->items)->toHaveCount(3)
            ->and($second->pagination->page)->toBe(2)
            ->and($second->pagination->perPage)->toBe(3)
            ->and(array_intersect($idsOf($second->items), $idsOf($first->items)))->toBe([]);
    });

    it('answers the last page with what is left over', function () {
        ($this->storeAppointments)(7);

        $page = ($this->list)(page: 3, perPage: 3)->value();

        expect($page->items)->toHaveCount(1)
            ->and($page->total)->toBe(7);
    });

    it('answers a page past the end with no rows and the real total', function () {
        ($this->storeAppointments)(2);

        $page = ($this->list)(page: 9, perPage: 3)->value();

        expect($page->items)->toBe([])
            ->and($page->total)->toBe(2);
    });

    it('hands the repository the pagination the input built', function () {
        ($this->list)(page: 4, perPage: 9999);

        $query = $this->appointments->customerQueries[0];

        expect($query->customerId)->toBe(AppointmentFixtures::CUSTOMER_ID)
            ->and($query->pagination->page)->toBe(4)
            ->and($query->pagination->perPage)->toBe(Pagination::MAXIMUM_PER_PAGE);
    });
});

describe('the customer it may not read', function () {
    it('refuses a customer of another business rather than answering an empty page', function () {
        $this->customers->add(AppointmentFixtures::OTHER_BUSINESS_ID, AppointmentFixtures::customerSnapshot(
            id: AppointmentFixtures::SECOND_CUSTOMER_ID,
        ));

        $response = ($this->list)(customerId: AppointmentFixtures::SECOND_CUSTOMER_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_customer_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->appointments->customerQueries)->toBe([]);
    });

    it('shows nothing of the appointments that customer has at the other business', function () {
        $this->customers->add(AppointmentFixtures::OTHER_BUSINESS_ID, AppointmentFixtures::customerSnapshot());
        $this->appointments->store(
            AppointmentFixtures::appointment(),
            AppointmentFixtures::appointment(
                id: AppointmentFixtures::FOREIGN_APPOINTMENT_ID,
                businessId: AppointmentFixtures::OTHER_BUSINESS_ID,
                startsAt: '2026-06-01T09:00:00+00:00',
                endsAt: '2026-06-01T10:00:00+00:00',
            ),
        );

        $page = ($this->list)()->value();

        expect($page->items)->toHaveCount(1)
            ->and($page->items[0]->id)->toBe(AppointmentFixtures::APPOINTMENT_ID)
            ->and($page->total)->toBe(1);
    });

    it('reads the business in context and never one a caller could name', function () {
        $this->appointments->store(AppointmentFixtures::appointment());

        ($this->list)();

        expect(array_unique($this->appointments->businessIdsSeen))->toBe([FakeBusinessContext::BUSINESS_ID])
            ->and($this->customers->batchReads[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('reads the other business when the context names that other business', function () {
        $this->customers->add(AppointmentFixtures::OTHER_BUSINESS_ID, AppointmentFixtures::customerSnapshot());

        ($this->build)(new FakeBusinessContext(AppointmentFixtures::OTHER_BUSINESS_ID))
            ->handle(AppointmentFixtures::listCustomerInput());

        expect(array_unique($this->appointments->businessIdsSeen))->toBe([AppointmentFixtures::OTHER_BUSINESS_ID]);
    });

    it('refuses a customer uuid no business carries', function () {
        $response = ($this->list)(customerId: AppointmentFixtures::UNKNOWN_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_customer_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->appointments->customerQueries)->toBe([]);
    });

    it('refuses a malformed uuid before it reads anything at all', function (string $customerId) {
        $response = ($this->list)(customerId: $customerId);

        expect($response->error()->code)->toBe('appointment_customer_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->journal->entries)->toBe([]);
    })->with([
        'a word' => 'not-a-uuid',
        'empty' => '',
        'an integer key' => '7',
    ]);
});

it('describes the whole page in one call per neighbour, never one per row', function () {
    $this->appointments->store(
        AppointmentFixtures::appointment(),
        AppointmentFixtures::appointment(
            id: AppointmentFixtures::SECOND_APPOINTMENT_ID,
            startsAt: '2026-04-10T09:00:00+00:00',
            endsAt: '2026-04-10T10:00:00+00:00',
        ),
        AppointmentFixtures::appointment(
            id: AppointmentFixtures::THIRD_APPOINTMENT_ID,
            startsAt: '2026-05-10T09:00:00+00:00',
            endsAt: '2026-05-10T10:00:00+00:00',
        ),
    );

    ($this->list)();

    expect($this->services->batchReads)->toHaveCount(1)
        ->and($this->services->reads)->toBe([])
        ->and($this->staff->batchReads)->toHaveCount(1)
        ->and($this->staff->reads)->toBe([])
        ->and($this->customers->batchReads)->toHaveCount(1);
});

describe('a caller who keeps only their own calendar', function () {
    beforeEach(function () {
        $this->calendars = FakeCalendarAccess::ownedBy(AppointmentFixtures::STAFF_ID);
        $this->staff->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::staffSnapshot(
            id: AppointmentFixtures::SECOND_STAFF_ID,
            name: AppointmentFixtures::SECOND_STAFF_NAME,
        ));

        $this->appointments->store(
            AppointmentFixtures::appointment(),
            AppointmentFixtures::appointment(
                id: AppointmentFixtures::SECOND_APPOINTMENT_ID,
                staffMemberId: AppointmentFixtures::SECOND_STAFF_ID,
                startsAt: '2026-04-10T09:00:00+00:00',
                endsAt: '2026-04-10T10:00:00+00:00',
            ),
            AppointmentFixtures::appointment(
                id: AppointmentFixtures::THIRD_APPOINTMENT_ID,
                startsAt: '2026-05-10T09:00:00+00:00',
                endsAt: '2026-05-10T10:00:00+00:00',
            ),
        );
    });

    it('answers with the history the customer has on their own calendar only', function () {
        $page = ($this->list)()->value();

        expect(array_map(static fn (AppointmentData $data): string => $data->id, $page->items))->toBe([
            AppointmentFixtures::THIRD_APPOINTMENT_ID,
            AppointmentFixtures::APPOINTMENT_ID,
        ]);
    });

    it('leaves out every row booked with another team member', function () {
        $page = ($this->list)()->value();

        expect(array_map(static fn (AppointmentData $data): string => $data->staffMember->id, $page->items))
            ->not->toContain(AppointmentFixtures::SECOND_STAFF_ID);
    });

    it('counts only the rows the caller may see, so the total leaks nothing either', function () {
        $page = ($this->list)(page: 1, perPage: 1)->value();

        expect($page->total)->toBe(2)
            ->and($page->lastPage())->toBe(2);
    });

    it('hands the repository the scope the caller was granted', function () {
        ($this->list)();

        expect($this->appointments->scopesSeen)->toHaveCount(1)
            ->and($this->appointments->scopesSeen[0]->restrictedStaffMemberId())->toBe(AppointmentFixtures::STAFF_ID);
    });

    it('answers the whole history to a caller who keeps every calendar', function () {
        $this->calendars = FakeCalendarAccess::everyone();

        expect(($this->list)()->value()->total)->toBe(3);
    });
});

describe('the calendar the caller is allowed to keep', function () {
    it('asks for the scope of the account on the input, in the business in context', function () {
        ($this->build)()->handle(AppointmentFixtures::listCustomerInput(accountId: AppointmentFixtures::SECOND_ACCOUNT_ID));

        expect($this->calendars->lookups)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'accountId' => AppointmentFixtures::SECOND_ACCOUNT_ID,
        ]]);
    });

    it('refuses an account that is no member of the business and reads no history', function () {
        $this->calendars = FakeCalendarAccess::refusing();
        $this->appointments->store(AppointmentFixtures::appointment());

        $response = ($this->list)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_accessible')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden)
            ->and($this->appointments->customerQueries)->toBe([]);
    });
});
