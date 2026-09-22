<?php

declare(strict_types=1);

use App\Domains\Payments\Application\Dtos\PaymentData;
use App\Domains\Payments\Application\Dtos\ShowAppointmentPaymentInput;
use App\Domains\Payments\Application\Presenters\PaymentPresenter;
use App\Domains\Payments\Application\UseCases\ShowAppointmentPayment;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Payments\FakePaymentMethodCatalog;
use Tests\Support\Payments\FakePaymentRepository;
use Tests\Support\Payments\PaymentFixtures;
use Tests\Support\Payments\PaymentJournal;

beforeEach(function () {
    $this->journal = new PaymentJournal;
    $this->payments = new FakePaymentRepository($this->journal);

    $this->paymentMethods = (new FakePaymentMethodCatalog($this->journal))
        ->register(PaymentFixtures::paymentMethod());

    $this->build = fn (?FakeBusinessContext $business = null): ShowAppointmentPayment => new ShowAppointmentPayment(
        $this->payments,
        new PaymentPresenter($this->paymentMethods),
        $business ?? new FakeBusinessContext,
    );

    $this->useCase = ($this->build)();

    $this->show = fn (string $appointmentId = PaymentFixtures::APPOINTMENT_ID) => $this->useCase
        ->handle(new ShowAppointmentPaymentInput($appointmentId));
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
            ->handle(new ShowAppointmentPaymentInput(PaymentFixtures::APPOINTMENT_ID));

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
