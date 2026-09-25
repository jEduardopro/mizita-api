<?php

declare(strict_types=1);

use App\Domains\Payments\Application\Dtos\PaymentData;
use App\Domains\Payments\Application\Dtos\ShowAppointmentPaymentInput;
use App\Domains\Payments\Application\Presenters\PaymentPresenter;
use App\Domains\Payments\Application\UseCases\ShowAppointmentPayment;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Payments\FakeAppointmentDirectory;
use Tests\Support\Payments\FakeCalendarAccess;
use Tests\Support\Payments\FakePaymentMethodCatalog;
use Tests\Support\Payments\FakePaymentRepository;
use Tests\Support\Payments\PaymentFixtures;
use Tests\Support\Payments\PaymentJournal;

beforeEach(function () {
    $this->journal = new PaymentJournal;
    $this->payments = new FakePaymentRepository($this->journal);

    $this->paymentMethods = (new FakePaymentMethodCatalog($this->journal))
        ->register(PaymentFixtures::paymentMethod());

    $this->appointments = (new FakeAppointmentDirectory($this->journal))
        ->add(
            FakeBusinessContext::BUSINESS_ID,
            PaymentFixtures::appointmentSnapshot(),
            PaymentFixtures::appointmentSnapshot(
                id: PaymentFixtures::OTHER_MEMBER_APPOINTMENT_ID,
                staffMemberId: PaymentFixtures::OTHER_STAFF_MEMBER_ID,
            ),
        );
    $this->calendars = FakeCalendarAccess::everyone();

    $this->build = fn (?FakeBusinessContext $business = null): ShowAppointmentPayment => new ShowAppointmentPayment(
        $this->payments,
        $this->appointments,
        $this->calendars,
        new PaymentPresenter($this->paymentMethods),
        $business ?? new FakeBusinessContext,
    );

    $this->show = fn (string $appointmentId = PaymentFixtures::APPOINTMENT_ID, string $actorAccountId = PaymentFixtures::ACTOR_ID) => ($this->build)()
        ->handle(new ShowAppointmentPaymentInput($appointmentId, $actorAccountId));
});

describe('an appointment somebody has already charged', function () {
    beforeEach(function () {
        $this->payments->store(PaymentFixtures::payment(
            transactions: [PaymentFixtures::transaction(amountCents: 20_000)],
        ));
    });

    it('answers with the payment the appointment carries', function () {
        $data = ($this->show)()->value();

        expect($data)->toBeInstanceOf(PaymentData::class)
            ->and($data->id)->toBe(PaymentFixtures::PAYMENT_ID)
            ->and($data->appointmentId)->toBe(PaymentFixtures::APPOINTMENT_ID)
            ->and($data->totalCents)->toBe(PaymentFixtures::SERVICE_PRICE_CENTS)
            ->and($data->paidCents)->toBe(20_000)
            ->and($data->balanceCents)->toBe(30_000)
            ->and($data->transactions)->toHaveCount(1);
    });

    it('asks the repository about the business in context', function () {
        ($this->show)();

        expect($this->payments->appointmentLookups)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'appointmentId' => PaymentFixtures::APPOINTMENT_ID,
        ]]);
    });

    it('shows nothing of a payment filed under another business', function () {
        $response = ($this->build)(new FakeBusinessContext(PaymentFixtures::OTHER_BUSINESS_ID))
            ->handle(new ShowAppointmentPaymentInput(PaymentFixtures::APPOINTMENT_ID, PaymentFixtures::ACTOR_ID));

        expect($response->failed())->toBeFalse()
            ->and($response->value())->toBeNull();
    });
});

describe('an appointment nobody has charged yet', function () {
    it('answers with a success carrying nothing at all', function () {
        $response = ($this->show)();

        expect($response->failed())->toBeFalse()
            ->and($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull();
    });

    it('reads the repository once and never reaches the presenter', function () {
        ($this->show)();

        expect($this->journal->entries)->toBe(['payments.findForAppointmentOrNull']);
    });

    it('treats an uncharged appointment as the ordinary case, not a refusal', function () {
        $this->payments->store(PaymentFixtures::payment(appointmentId: PaymentFixtures::SECOND_APPOINTMENT_ID));

        expect(($this->show)(PaymentFixtures::APPOINTMENT_ID)->value())->toBeNull()
            ->and(($this->show)(PaymentFixtures::SECOND_APPOINTMENT_ID)->value())
            ->toBeInstanceOf(PaymentData::class);
    });
});

describe('refusing to look', function () {
    it('refuses an appointment identifier that is no uuid', function (string $appointmentId) {
        $response = ($this->show)($appointmentId);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_appointment_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    })->with([
        'empty' => '',
        'whitespace only' => '   ',
        'a sequential int' => '1',
        'a word' => 'not-a-uuid',
    ]);

    it('refuses before it asks the repository anything', function () {
        ($this->show)('not-a-uuid');

        expect($this->journal->entries)->toBe([]);
    });
});

describe('a caller who keeps every calendar', function () {
    it('never asks the appointment directory whose calendar the appointment is on', function () {
        $this->payments->store(PaymentFixtures::payment());

        ($this->show)();

        expect($this->appointments->reads)->toBe([]);
    });

    it('still answers nothing, not a refusal, for an appointment nobody booked', function () {
        $response = ($this->show)(PaymentFixtures::UNKNOWN_ID);

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull();
    });

    it('sees the payment of an appointment on the calendar of any team member', function () {
        $this->payments->store(PaymentFixtures::payment(appointmentId: PaymentFixtures::OTHER_MEMBER_APPOINTMENT_ID));

        expect(($this->show)(PaymentFixtures::OTHER_MEMBER_APPOINTMENT_ID)->value()?->appointmentId)
            ->toBe(PaymentFixtures::OTHER_MEMBER_APPOINTMENT_ID);
    });
});

describe('a caller who keeps only their own calendar', function () {
    beforeEach(function () {
        $this->calendars = FakeCalendarAccess::ownedBy(PaymentFixtures::STAFF_MEMBER_ID);
        $this->payments->store(
            PaymentFixtures::payment(),
            PaymentFixtures::payment(
                id: PaymentFixtures::UNKNOWN_ID,
                appointmentId: PaymentFixtures::OTHER_MEMBER_APPOINTMENT_ID,
            ),
        );
    });

    it('sees the payment of an appointment on their own calendar', function () {
        $data = ($this->show)()->value();

        expect($data)->toBeInstanceOf(PaymentData::class)
            ->and($data->id)->toBe(PaymentFixtures::PAYMENT_ID)
            ->and($data->appointmentId)->toBe(PaymentFixtures::APPOINTMENT_ID);
    });

    it('answers not found for the payment of an appointment on another team member calendar', function () {
        $response = ($this->show)(PaymentFixtures::OTHER_MEMBER_APPOINTMENT_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_appointment_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('never reads the payment of an appointment it refused', function () {
        ($this->show)(PaymentFixtures::OTHER_MEMBER_APPOINTMENT_ID);

        expect($this->payments->appointmentLookups)->toBe([])
            ->and($this->journal->entries)->toBe(['appointments.describe']);
    });

    it('asks the directory about the appointment in the business in context', function () {
        ($this->show)();

        expect($this->appointments->reads)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'appointmentId' => PaymentFixtures::APPOINTMENT_ID,
        ]]);
    });

    it('answers not found for an appointment nobody booked', function () {
        $response = ($this->show)(PaymentFixtures::UNKNOWN_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_appointment_not_found');
    });
});

describe('the calendar the caller is allowed to keep', function () {
    it('asks for the scope of the actor, in the business in context', function () {
        ($this->show)(PaymentFixtures::APPOINTMENT_ID, PaymentFixtures::OTHER_ACTOR_ID);

        expect($this->calendars->lookups)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'accountId' => PaymentFixtures::OTHER_ACTOR_ID,
        ]]);
    });

    it('refuses an actor that is no member of the business and reads nothing', function () {
        $this->calendars = FakeCalendarAccess::refusing();

        $response = ($this->show)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_account_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->journal->entries)->toBe([]);
    });

    it('refuses a malformed actor before it asks for any scope', function () {
        $response = ($this->show)(PaymentFixtures::APPOINTMENT_ID, 'the-owner');

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_payment_actor')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->calendars->lookups)->toBe([])
            ->and($this->journal->entries)->toBe([]);
    });
});
