<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\Dtos\AppointmentCustomerData;
use App\Domains\Appointments\Application\Dtos\AppointmentData;
use App\Domains\Appointments\Application\Dtos\AppointmentServiceData;
use App\Domains\Appointments\Application\Presenters\AppointmentPresenter;
use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\Exceptions\AppointmentServiceNotFound;
use App\Domains\Appointments\Exceptions\AppointmentStaffNotFound;
use App\Domains\Appointments\ValueObjects\AppointmentPaymentStatus;
use App\Shared\ValueObjects\Paginated;
use App\Shared\ValueObjects\Pagination;
use Tests\Support\Appointments\AppointmentFixtures;
use Tests\Support\Appointments\AppointmentJournal;
use Tests\Support\Appointments\FakeCustomerDirectory;
use Tests\Support\Appointments\FakePaymentLedger;
use Tests\Support\Appointments\FakeServiceCatalog;
use Tests\Support\Appointments\FakeStaffDirectory;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->journal = new AppointmentJournal;

    $this->services = (new FakeServiceCatalog($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::serviceSnapshot());
    $this->customers = (new FakeCustomerDirectory($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::customerSnapshot(
            phone: AppointmentFixtures::customerPhoneSnapshot(),
        ));
    $this->staff = (new FakeStaffDirectory($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::staffSnapshot());

    $this->payments = new FakePaymentLedger($this->journal);

    $this->presenter = new AppointmentPresenter($this->services, $this->customers, $this->staff, $this->payments);

    $this->pageOf = fn (array $appointments, int $total, Pagination $pagination): Paginated => $this->presenter
        ->describePage(FakeBusinessContext::BUSINESS_ID, Paginated::of($appointments, $total, $pagination));
});

describe('describing one appointment', function () {
    it('carries what the service costs and how long it blocks', function () {
        $data = $this->presenter->describe(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::appointment());

        expect($data->service)->toBeInstanceOf(AppointmentServiceData::class)
            ->and($data->service->durationMinutes)->toBe(AppointmentFixtures::SERVICE_DURATION_MINUTES)
            ->and($data->service->bufferMinutes)->toBe(AppointmentFixtures::SERVICE_BUFFER_MINUTES)
            ->and($data->service->price)->toBe(AppointmentFixtures::SERVICE_PRICE)
            ->and($data->service->price)->toBeString();
    });

    it('carries the phone the customer can be reached on', function () {
        $data = $this->presenter->describe(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::appointment());

        expect($data->customer)->toBeInstanceOf(AppointmentCustomerData::class)
            ->and($data->customer->phone?->countryCode)->toBe(AppointmentFixtures::CUSTOMER_PHONE_COUNTRY_CODE)
            ->and($data->customer->phone?->nationalNumber)->toBe(AppointmentFixtures::CUSTOMER_PHONE_NATIONAL_NUMBER);
    });

    it('carries no phone for a customer who left none', function () {
        $this->customers->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::customerSnapshot(
            id: AppointmentFixtures::SECOND_CUSTOMER_ID,
        ));

        $data = $this->presenter->describe(
            FakeBusinessContext::BUSINESS_ID,
            AppointmentFixtures::appointment(customerId: AppointmentFixtures::SECOND_CUSTOMER_ID),
        );

        expect($data->customer->phone)->toBeNull();
    });
});

describe('the payment badge an appointment carries', function () {
    it('carries no payment status for an appointment nobody has charged', function () {
        $data = $this->presenter->describe(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::appointment());

        expect($data->paymentStatus)->toBeNull();
    });

    it('carries the status the ledger recorded', function (AppointmentPaymentStatus $status) {
        $this->payments->add(
            FakeBusinessContext::BUSINESS_ID,
            AppointmentFixtures::APPOINTMENT_ID,
            AppointmentFixtures::paymentSnapshot(status: $status),
        );

        $data = $this->presenter->describe(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::appointment());

        expect($data->paymentStatus)->toBe($status);
    })->with([
        'pending' => AppointmentPaymentStatus::Pending,
        'partially paid' => AppointmentPaymentStatus::PartiallyPaid,
        'paid' => AppointmentPaymentStatus::Paid,
    ]);

    it('asks the ledger about the appointment of the business it was given', function () {
        $this->presenter->describe(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::appointment());

        expect($this->payments->reads)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'appointmentId' => AppointmentFixtures::APPOINTMENT_ID,
        ]]);
    });

    it('carries no payment status for a payment filed under another business', function () {
        $this->payments->add(
            AppointmentFixtures::OTHER_BUSINESS_ID,
            AppointmentFixtures::APPOINTMENT_ID,
            AppointmentFixtures::paymentSnapshot(),
        );

        $data = $this->presenter->describe(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::appointment());

        expect($data->paymentStatus)->toBeNull();
    });
});

describe('describing a page', function () {
    beforeEach(function () {
        $this->first = AppointmentFixtures::appointment();
        $this->second = AppointmentFixtures::appointment(
            id: AppointmentFixtures::SECOND_APPOINTMENT_ID,
            startsAt: '2026-04-10T09:00:00+00:00',
            endsAt: '2026-04-10T10:00:00+00:00',
        );
        $this->third = AppointmentFixtures::appointment(
            id: AppointmentFixtures::THIRD_APPOINTMENT_ID,
            startsAt: '2026-05-10T09:00:00+00:00',
            endsAt: '2026-05-10T10:00:00+00:00',
        );
    });

    it('keeps the order the repository handed it', function () {
        $page = ($this->pageOf)([$this->third, $this->first, $this->second], 3, Pagination::of(1, 20));

        expect(array_map(static fn (AppointmentData $data): string => $data->id, $page->items))->toBe([
            AppointmentFixtures::THIRD_APPOINTMENT_ID,
            AppointmentFixtures::APPOINTMENT_ID,
            AppointmentFixtures::SECOND_APPOINTMENT_ID,
        ]);
    });

    it('keeps the total and the pagination of the page it was given', function () {
        $page = ($this->pageOf)([$this->first], 42, Pagination::of(3, 5));

        expect($page)->toBeInstanceOf(Paginated::class)
            ->and($page->total)->toBe(42)
            ->and($page->pagination->page)->toBe(3)
            ->and($page->pagination->perPage)->toBe(5)
            ->and($page->lastPage())->toBe(9);
    });

    it('asks each neighbour once for the whole page, never once per row', function () {
        ($this->pageOf)([$this->first, $this->second, $this->third], 3, Pagination::of(1, 20));

        expect($this->customers->batchReads)->toHaveCount(1)
            ->and($this->customers->reads)->toBe([])
            ->and($this->services->batchReads)->toHaveCount(1)
            ->and($this->services->reads)->toBe([])
            ->and($this->staff->batchReads)->toHaveCount(1)
            ->and($this->staff->reads)->toBe([]);
    });

    it('asks each neighbour for one id when three rows share the same neighbour', function () {
        ($this->pageOf)([$this->first, $this->second, $this->third], 3, Pagination::of(1, 20));

        expect($this->customers->batchReads[0]['customerIds'])->toBe([AppointmentFixtures::CUSTOMER_ID])
            ->and($this->services->batchReads[0]['serviceIds'])->toBe([AppointmentFixtures::SERVICE_ID])
            ->and($this->staff->batchReads[0]['staffMemberIds'])->toBe([AppointmentFixtures::STAFF_ID]);
    });

    it('asks nobody anything for an empty page', function () {
        $page = ($this->pageOf)([], 0, Pagination::of(1, 20));

        expect($page->items)->toBe([])
            ->and($page->total)->toBe(0)
            ->and($this->journal->entries)->toBe([]);
    });

    it('reads the neighbours of the business it was asked about', function () {
        ($this->pageOf)([$this->first], 1, Pagination::of(1, 20));

        expect($this->customers->batchReads[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->services->batchReads[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->staff->batchReads[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('refuses the page when a neighbour of one row is missing', function (callable $appointment, string $exception) {
        expect(fn () => ($this->pageOf)([$appointment()], 1, Pagination::of(1, 20)))->toThrow($exception);
    })->with([
        'an unknown customer' => [
            fn () => AppointmentFixtures::appointment(customerId: AppointmentFixtures::UNKNOWN_ID),
            AppointmentCustomerNotFound::class,
        ],
        'an unknown service' => [
            fn () => AppointmentFixtures::appointment(serviceId: AppointmentFixtures::UNKNOWN_ID),
            AppointmentServiceNotFound::class,
        ],
        'an unknown staff member' => [
            fn () => AppointmentFixtures::appointment(staffMemberId: AppointmentFixtures::UNKNOWN_ID),
            AppointmentStaffNotFound::class,
        ],
    ]);

    it('shows nothing of a neighbour filed under another business', function () {
        $this->customers->add(AppointmentFixtures::OTHER_BUSINESS_ID, AppointmentFixtures::customerSnapshot(
            id: AppointmentFixtures::SECOND_CUSTOMER_ID,
        ));

        expect(fn () => ($this->pageOf)(
            [AppointmentFixtures::appointment(customerId: AppointmentFixtures::SECOND_CUSTOMER_ID)],
            1,
            Pagination::of(1, 20),
        ))->toThrow(AppointmentCustomerNotFound::class);
    });
});

describe('the payment badge each row of a page carries', function () {
    beforeEach(function () {
        $this->charged = AppointmentFixtures::appointment();
        $this->uncharged = AppointmentFixtures::appointment(
            id: AppointmentFixtures::SECOND_APPOINTMENT_ID,
            startsAt: '2026-04-10T09:00:00+00:00',
            endsAt: '2026-04-10T10:00:00+00:00',
        );

        $this->payments->add(
            FakeBusinessContext::BUSINESS_ID,
            AppointmentFixtures::APPOINTMENT_ID,
            AppointmentFixtures::paymentSnapshot(),
        );

        $this->statusesOf = fn (array $appointments): array => array_map(
            static fn (AppointmentData $data): ?AppointmentPaymentStatus => $data->paymentStatus,
            $this->presenter->describeMany(FakeBusinessContext::BUSINESS_ID, $appointments),
        );
    });

    it('badges the charged row and leaves the uncharged one bare', function () {
        expect(($this->statusesOf)([$this->charged, $this->uncharged]))
            ->toBe([AppointmentPaymentStatus::Paid, null]);
    });

    it('keeps the badge on its own row whatever order the page arrives in', function () {
        expect(($this->statusesOf)([$this->uncharged, $this->charged]))
            ->toBe([null, AppointmentPaymentStatus::Paid]);
    });

    it('looks the payment up by the appointment uuid, never by the payment uuid', function () {
        $statuses = ($this->statusesOf)([
            $this->charged,
            AppointmentFixtures::appointment(id: AppointmentFixtures::PAYMENT_ID),
        ]);

        expect($statuses)->toBe([AppointmentPaymentStatus::Paid, null]);
    });

    it('asks the ledger for the appointment uuids of the page, deduplicated', function () {
        ($this->statusesOf)([$this->charged, $this->uncharged, $this->charged]);

        expect($this->payments->batchReads)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'appointmentIds' => [
                AppointmentFixtures::APPOINTMENT_ID,
                AppointmentFixtures::SECOND_APPOINTMENT_ID,
            ],
        ]]);
    });

    it('asks the ledger once for a page of many appointments, never once per row', function () {
        $appointments = array_map(
            static fn (int $index) => AppointmentFixtures::appointment(
                id: sprintf('01930000-0000-7000-8000-0000000%05d', $index),
            ),
            range(1, 25),
        );

        ($this->statusesOf)($appointments);

        expect($this->payments->batchReads)->toHaveCount(1)
            ->and($this->payments->batchReads[0]['appointmentIds'])->toHaveCount(25)
            ->and($this->payments->reads)->toBe([])
            ->and(array_keys($this->journal->entries, 'payments.describeMany', true))->toHaveCount(1)
            ->and($this->journal->entries)->not->toContain('payments.describe');
    });

    it('asks the ledger nothing at all for an empty page', function () {
        expect($this->presenter->describeMany(FakeBusinessContext::BUSINESS_ID, []))->toBe([])
            ->and($this->payments->batchReads)->toBe([])
            ->and($this->journal->entries)->toBe([]);
    });
});
