<?php

declare(strict_types=1);

use App\Domains\Payments\Application\Dtos\PaymentData;
use App\Domains\Payments\Application\Dtos\PaymentTransactionData;
use App\Domains\Payments\Application\Presenters\PaymentPresenter;
use App\Domains\Payments\Application\UseCases\VoidPaymentTransaction;
use App\Domains\Payments\Entities\PaymentTransaction;
use App\Domains\Payments\ValueObjects\DiscountType;
use App\Domains\Payments\ValueObjects\PaymentStatus;
use App\Domains\Payments\ValueObjects\PaymentTransactionType;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;
use Tests\Support\Payments\FakeAppointmentDirectory;
use Tests\Support\Payments\FakeCalendarAccess;
use Tests\Support\Payments\FakePaymentMethodCatalog;
use Tests\Support\Payments\FakePaymentRepository;
use Tests\Support\Payments\PaymentFixtures;
use Tests\Support\Payments\PaymentJournal;

beforeEach(function () {
    $this->voidedAt = '2026-03-11T15:00:00+00:00';
    $this->journal = new PaymentJournal;
    $this->transactions = new FakeTransactionManager;
    $this->ids = new FixedIdGenerator(...PaymentFixtures::voidIds(5));

    $this->payments = (new FakePaymentRepository($this->journal, $this->transactions))
        ->store(PaymentFixtures::payment(transactions: [
            PaymentFixtures::transaction(
                id: PaymentFixtures::TRANSACTION_ID,
                amountCents: 20_000,
                breakdown: PaymentFixtures::breakdown(50_000),
                accountId: PaymentFixtures::ACTOR_ID,
            ),
            PaymentFixtures::transaction(
                id: PaymentFixtures::SECOND_TRANSACTION_ID,
                paymentMethodId: PaymentFixtures::CARD_METHOD_ID,
                amountCents: 30_000,
            ),
        ]));

    $this->paymentMethods = (new FakePaymentMethodCatalog($this->journal))->register(
        PaymentFixtures::paymentMethod(PaymentFixtures::CASH_METHOD_ID, 'cash', 1),
        PaymentFixtures::paymentMethod(PaymentFixtures::CARD_METHOD_ID, 'card', 2),
    );

    $this->appointments = (new FakeAppointmentDirectory($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, PaymentFixtures::appointmentSnapshot());
    $this->calendars = FakeCalendarAccess::everyone();

    $this->build = fn (?FakeBusinessContext $business = null): VoidPaymentTransaction => new VoidPaymentTransaction(
        $this->payments,
        $this->appointments,
        $this->calendars,
        new PaymentPresenter($this->paymentMethods),
        $this->ids,
        new FakeClock(PaymentFixtures::instant($this->voidedAt)),
        $this->transactions,
        $business ?? new FakeBusinessContext,
    );

    $this->void = fn (...$overrides) => ($this->build)()
        ->handle(PaymentFixtures::voidInput(...$overrides));
});

describe('voiding a transaction', function () {
    it('answers with the payment the void left behind', function () {
        $data = ($this->void)()->value();

        expect($data)->toBeInstanceOf(PaymentData::class)
            ->and($data->id)->toBe(PaymentFixtures::PAYMENT_ID)
            ->and($data->totalCents)->toBe(PaymentFixtures::SERVICE_PRICE_CENTS)
            ->and($data->paidCents)->toBe(30_000)
            ->and($data->balanceCents)->toBe(20_000)
            ->and($data->status)->toBe(PaymentStatus::PartiallyPaid);
    });

    it('appends a void row and leaves the charge it reverses approved', function () {
        $data = ($this->void)()->value();

        expect($data->transactions)->toHaveCount(3)
            ->and($data->transactions[0]->id)->toBe(PaymentFixtures::TRANSACTION_ID)
            ->and($data->transactions[0]->type)->toBe(PaymentTransactionType::Approved)
            ->and($data->transactions[2]->id)->toBe(PaymentFixtures::GENERATED_VOID_ID)
            ->and($data->transactions[2]->type)->toBe(PaymentTransactionType::Void)
            ->and($data->transactions[2]->totalCents)->toBe(20_000);
    });

    it('stamps the void row with the identity and the clock it was handed', function () {
        $void = ($this->void)()->value()->transactions[2];

        expect($void->id)->toBe(PaymentFixtures::GENERATED_VOID_ID)
            ->and($void->paymentMethodId)->toBe(PaymentFixtures::CASH_METHOD_ID)
            ->and($void->paymentMethodCode)->toBe('cash')
            ->and($void->processedAt)->toEqual(PaymentFixtures::instant($this->voidedAt));
    });

    it('prices nothing on the void row it appends', function () {
        $void = ($this->void)()->value()->transactions[2];

        expect($void->subtotalPreDiscountCents)->toBe(0)
            ->and($void->subtotalDiscountCents)->toBe(0)
            ->and($void->subtotalCents)->toBe(0)
            ->and($void->discountType)->toBe(DiscountType::None)
            ->and($void->discountValue)->toBe(0);
    });

    it('leaves the other transactions alone', function () {
        $transaction = ($this->void)()->value()->transactions[1];

        expect($transaction->id)->toBe(PaymentFixtures::SECOND_TRANSACTION_ID)
            ->and($transaction->type)->toBe(PaymentTransactionType::Approved)
            ->and($transaction->totalCents)->toBe(30_000);
    });

    it('leaves a payment whose every charge was voided owing the whole total', function () {
        ($this->void)();
        $data = ($this->void)(transactionId: PaymentFixtures::SECOND_TRANSACTION_ID)->value();

        expect($data->paidCents)->toBe(0)
            ->and($data->balanceCents)->toBe(PaymentFixtures::SERVICE_PRICE_CENTS)
            ->and($data->status)->toBe(PaymentStatus::Pending)
            ->and($data->transactions)->toHaveCount(4);
    });

    it('saves the payment it mutated', function () {
        ($this->void)();

        expect($this->payments->saved)->toHaveCount(1)
            ->and($this->payments->saved[0]->id)->toBe(PaymentFixtures::PAYMENT_ID)
            ->and($this->payments->saved[0]->transactions())->toHaveCount(3);
    });
});

describe('the account that answers for the void', function () {
    it('records the actor the caller was authenticated as', function () {
        ($this->void)();

        $voids = array_values(array_filter(
            $this->payments->saved[0]->transactions(),
            static fn (PaymentTransaction $transaction): bool => $transaction->type === PaymentTransactionType::Void,
        ));

        expect($voids)->toHaveCount(1)
            ->and($voids[0]->id)->toBe(PaymentFixtures::GENERATED_VOID_ID)
            ->and($voids[0]->accountId)->toBe(PaymentFixtures::ACTOR_ID);
    });

    it('records whichever actor the input names', function () {
        ($this->void)(actorAccountId: PaymentFixtures::OTHER_ACTOR_ID);

        expect($this->payments->saved[0]->transactions()[2]->accountId)
            ->toBe(PaymentFixtures::OTHER_ACTOR_ID);
    });

    it('keeps the actor out of what the client reads back', function () {
        $keys = array_map(
            static fn (PaymentTransactionData $data): array => array_keys(get_object_vars($data)),
            ($this->void)()->value()->transactions,
        );

        expect(array_unique(array_merge(...$keys)))
            ->not->toContain('accountId')
            ->not->toContain('voidedByAccountId');
    });
});

describe('the row it has to hold still', function () {
    it('locks the payment before it mutates it', function () {
        ($this->void)();

        expect($this->payments->locks)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'paymentId' => PaymentFixtures::PAYMENT_ID,
        ]]);
    });

    it('opens exactly one transaction and saves inside it', function () {
        ($this->void)();

        expect($this->transactions->runs())->toBe(1)
            ->and($this->payments->savedInsideTransaction)->toBe([true]);
    });

    it('locks, saves, then describes what it committed', function () {
        ($this->void)();

        expect($this->journal->entries)->toBe([
            'payments.lockForBusiness',
            'payments.save',
            'paymentMethods.describeMany',
        ]);
    });
});

describe('refusing to void', function () {
    it('refuses what the input itself refuses, before it asks any neighbour', function (array $overrides, string $code) {
        $response = ($this->void)(...$overrides);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($this->journal->entries)->toBe([])
            ->and($this->transactions->runs())->toBe(0)
            ->and($this->payments->saved)->toBe([]);
    })->with([
        'a payment id that is no uuid' => [['paymentId' => 'not-a-uuid'], 'payment_not_found'],
        'a transaction id that is no uuid' => [['transactionId' => ''], 'payment_transaction_not_found'],
        'an actor id that is no uuid' => [['actorAccountId' => 'the-owner'], 'invalid_void_actor'],
    ]);

    it('refuses a payment nobody opened here', function () {
        $response = ($this->void)(paymentId: PaymentFixtures::UNKNOWN_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->payments->saved)->toBe([]);
    });

    it('refuses a transaction the payment never carried', function () {
        $response = ($this->void)(transactionId: PaymentFixtures::THIRD_TRANSACTION_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_transaction_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->payments->saved)->toBe([]);
    });

    it('refuses to void a row that is not an approved charge', function () {
        ($this->void)();

        $response = ($this->void)(transactionId: PaymentFixtures::GENERATED_VOID_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_transaction_not_voidable')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->payments->saved)->toHaveCount(1);
    });

    it('refuses a void once the payment holds no money at all', function () {
        ($this->void)();
        ($this->void)(transactionId: PaymentFixtures::SECOND_TRANSACTION_ID);

        $response = ($this->void)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('void_exceeds_paid_amount')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->payments->saved)->toHaveCount(2);
    });

    it('refuses a void larger than what is still collected', function () {
        ($this->void)(transactionId: PaymentFixtures::SECOND_TRANSACTION_ID);

        $response = ($this->void)(transactionId: PaymentFixtures::SECOND_TRANSACTION_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('void_exceeds_paid_amount')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->payments->saved)->toHaveCount(1);
    });

    it('shows nothing of a payment filed under another business', function () {
        $response = ($this->build)(new FakeBusinessContext(PaymentFixtures::OTHER_BUSINESS_ID))
            ->handle(PaymentFixtures::voidInput());

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_not_found')
            ->and($this->payments->locks)->toBe([[
                'businessId' => PaymentFixtures::OTHER_BUSINESS_ID,
                'paymentId' => PaymentFixtures::PAYMENT_ID,
            ]])
            ->and($this->payments->saved)->toBe([]);
    });
});

describe('a caller who keeps every calendar', function () {
    it('never asks the appointment directory whose calendar the payment is on', function () {
        ($this->void)();

        expect($this->appointments->reads)->toBe([]);
    });
});

describe('a caller who keeps only their own calendar', function () {
    it('voids a transaction on a payment of their own calendar', function () {
        $this->calendars = FakeCalendarAccess::ownedBy(PaymentFixtures::STAFF_MEMBER_ID);

        $data = ($this->void)()->value();

        expect($data->transactions)->toHaveCount(3)
            ->and($data->transactions[2]->type)->toBe(PaymentTransactionType::Void);
    });

    it('checks the calendar of the locked payment before it voids anything', function () {
        $this->calendars = FakeCalendarAccess::ownedBy(PaymentFixtures::STAFF_MEMBER_ID);

        ($this->void)();

        expect($this->journal->entries)->toBe([
            'payments.lockForBusiness',
            'appointments.describe',
            'payments.save',
            'paymentMethods.describeMany',
        ]);
    });

    it('answers not found for a payment on the calendar of another team member', function () {
        $this->calendars = FakeCalendarAccess::ownedBy(PaymentFixtures::OTHER_STAFF_MEMBER_ID);

        $response = ($this->void)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('writes nothing to a payment on the calendar of another team member', function () {
        $this->calendars = FakeCalendarAccess::ownedBy(PaymentFixtures::OTHER_STAFF_MEMBER_ID);

        ($this->void)();

        expect($this->payments->saved)->toBe([])
            ->and($this->journal->entries)->not->toContain('payments.save')
            ->and($this->payments->findForBusiness(FakeBusinessContext::BUSINESS_ID, PaymentFixtures::PAYMENT_ID)->transactions())
            ->toHaveCount(2);
    });
});

describe('the calendar the caller is allowed to keep', function () {
    it('asks for the scope of the actor, in the business in context', function () {
        ($this->void)(actorAccountId: PaymentFixtures::OTHER_ACTOR_ID);

        expect($this->calendars->lookups)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'accountId' => PaymentFixtures::OTHER_ACTOR_ID,
        ]]);
    });

    it('refuses an actor that is no member of the business before it locks anything', function () {
        $this->calendars = FakeCalendarAccess::refusing();

        $response = ($this->void)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('payment_account_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->payments->locks)->toBe([])
            ->and($this->transactions->runs())->toBe(0)
            ->and($this->payments->saved)->toBe([]);
    });
});
