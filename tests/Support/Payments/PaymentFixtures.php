<?php

declare(strict_types=1);

namespace Tests\Support\Payments;

use App\Domains\Payments\Application\Dtos\CreateAppointmentPaymentInput;
use App\Domains\Payments\Application\Dtos\DiscountInput;
use App\Domains\Payments\Application\Dtos\PaymentAddOnInput;
use App\Domains\Payments\Application\Dtos\RecordPaymentTransactionInput;
use App\Domains\Payments\Application\Dtos\VoidPaymentTransactionInput;
use App\Domains\Payments\Entities\Payment;
use App\Domains\Payments\Entities\PaymentItem;
use App\Domains\Payments\Entities\PaymentMethod;
use App\Domains\Payments\Entities\PaymentTransaction;
use App\Domains\Payments\ValueObjects\AppointmentSnapshot;
use App\Domains\Payments\ValueObjects\Discount;
use App\Domains\Payments\ValueObjects\Money;
use App\Domains\Payments\ValueObjects\PaymentBreakdown;
use App\Domains\Payments\ValueObjects\PaymentItemName;
use App\Domains\Payments\ValueObjects\PaymentTransactionType;
use App\Shared\ValueObjects\CurrencyCode;
use DateTimeImmutable;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FixedIdGenerator;

final class PaymentFixtures
{
    public const NOW = '2026-03-10T09:00:00+00:00';

    public const OTHER_BUSINESS_ID = '01930000-0000-7000-8000-0000000000b2';

    public const APPOINTMENT_ID = '01930000-0000-7000-8000-0000000000a1';

    public const SECOND_APPOINTMENT_ID = '01930000-0000-7000-8000-0000000000a2';

    public const SERVICE_ID = '01930000-0000-7000-8000-0000000000c1';

    public const SERVICE_NAME = 'Classic Haircut';

    public const SERVICE_PRICE_CENTS = 50_000;

    public const PAYMENT_ID = '01930000-0000-7000-8000-0000000000d1';

    public const CASH_METHOD_ID = '01930000-0000-7000-8000-0000000000e1';

    public const CARD_METHOD_ID = '01930000-0000-7000-8000-0000000000e2';

    public const TRANSFER_METHOD_ID = '01930000-0000-7000-8000-0000000000e3';

    public const TRANSACTION_ID = '01930000-0000-7000-8000-0000000000f1';

    public const SECOND_TRANSACTION_ID = '01930000-0000-7000-8000-0000000000f2';

    public const THIRD_TRANSACTION_ID = '01930000-0000-7000-8000-0000000000f3';

    public const ITEM_ID = '01930000-0000-7000-8000-000000000a11';

    public const ACTOR_ID = '01930000-0000-7000-8000-00000000ac01';

    public const OTHER_ACTOR_ID = '01930000-0000-7000-8000-00000000ac02';

    public const UNKNOWN_ID = '01930000-0000-7000-8000-000000000999';

    public const GENERATED_PAYMENT_ID = '01930000-0000-7000-8000-000000001001';

    public const GENERATED_SERVICE_ITEM_ID = '01930000-0000-7000-8000-000000001002';

    public const GENERATED_FIRST_ADD_ON_ID = '01930000-0000-7000-8000-000000002001';

    public const GENERATED_SECOND_ADD_ON_ID = '01930000-0000-7000-8000-000000002002';

    public const GENERATED_TRANSACTION_ID = '01930000-0000-7000-8000-000000001099';

    public const GENERATED_VOID_ID = '01930000-0000-7000-8000-000000003001';

    public const GENERATED_SECOND_VOID_ID = '01930000-0000-7000-8000-000000003002';

    public const CURRENCY = 'MXN';

    public const OTHER_CURRENCY = 'USD';

    public static function now(): DateTimeImmutable
    {
        return self::instant(self::NOW);
    }

    public static function instant(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value);
    }

    public static function currency(string $code = self::CURRENCY): CurrencyCode
    {
        return CurrencyCode::fromString($code);
    }

    public static function money(int $cents, string $code = self::CURRENCY): Money
    {
        return Money::fromCents($cents, self::currency($code));
    }

    public static function appointmentSnapshot(
        string $id = self::APPOINTMENT_ID,
        string $serviceId = self::SERVICE_ID,
        bool $cancelled = false,
    ): AppointmentSnapshot {
        return new AppointmentSnapshot($id, $serviceId, $cancelled);
    }

    public static function paymentMethod(
        string $id = self::CASH_METHOD_ID,
        string $code = 'cash',
        int $position = 1,
        bool $active = true,
        bool $requiresIntegration = false,
    ): PaymentMethod {
        return PaymentMethod::restore($id, $code, $position, $active, $requiresIntegration);
    }

    public static function item(
        string $id = self::ITEM_ID,
        string $name = self::SERVICE_NAME,
        int $amountCents = self::SERVICE_PRICE_CENTS,
        int $position = 0,
        string $currencyCode = self::CURRENCY,
    ): PaymentItem {
        return PaymentItem::restore(
            $id,
            PaymentItemName::restore($name),
            self::money($amountCents, $currencyCode),
            $position,
        );
    }

    public static function breakdown(
        int $subtotalPreDiscountCents = self::SERVICE_PRICE_CENTS,
        ?Discount $discount = null,
        string $currencyCode = self::CURRENCY,
    ): PaymentBreakdown {
        return PaymentBreakdown::of(
            self::money($subtotalPreDiscountCents, $currencyCode),
            $discount ?? Discount::none(),
        );
    }

    public static function transaction(
        string $id = self::TRANSACTION_ID,
        string $paymentMethodId = self::CASH_METHOD_ID,
        int $amountCents = self::SERVICE_PRICE_CENTS,
        ?string $processedAt = null,
        ?string $voidedAt = null,
        ?string $voidedByAccountId = null,
        string $currencyCode = self::CURRENCY,
        PaymentTransactionType $type = PaymentTransactionType::Approved,
        ?PaymentBreakdown $breakdown = null,
        ?string $accountId = null,
    ): PaymentTransaction {
        if ($voidedAt !== null) {
            return PaymentTransaction::restore(
                $id,
                PaymentTransactionType::Void,
                $paymentMethodId,
                $voidedByAccountId,
                PaymentBreakdown::none(self::currency($currencyCode)),
                self::money($amountCents, $currencyCode),
                self::instant($voidedAt),
            );
        }

        return PaymentTransaction::restore(
            $id,
            $type,
            $paymentMethodId,
            $accountId,
            $breakdown ?? PaymentBreakdown::none(self::currency($currencyCode)),
            self::money($amountCents, $currencyCode),
            self::instant($processedAt ?? self::NOW),
        );
    }

    /**
     * @param  list<PaymentItem>  $items
     * @param  list<PaymentTransaction>  $transactions
     */
    public static function payment(
        string $id = self::PAYMENT_ID,
        ?string $businessId = null,
        string $appointmentId = self::APPOINTMENT_ID,
        string $currencyCode = self::CURRENCY,
        ?array $items = null,
        ?Discount $discount = null,
        array $transactions = [],
        ?string $createdAt = null,
    ): Payment {
        return Payment::restore(
            id: $id,
            businessId: $businessId ?? FakeBusinessContext::BUSINESS_ID,
            appointmentId: $appointmentId,
            currency: self::currency($currencyCode),
            items: $items ?? [self::item(currencyCode: $currencyCode)],
            discount: $discount ?? Discount::none(),
            transactions: $transactions,
            createdAt: self::instant($createdAt ?? self::NOW),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function createPayload(array $overrides = []): array
    {
        return array_replace([
            'add_ons' => [],
            'payment_method_id' => self::CASH_METHOD_ID,
            'amount_cents' => self::SERVICE_PRICE_CENTS,
        ], $overrides);
    }

    /**
     * @param  list<PaymentAddOnInput>  $addOns
     */
    public static function createInput(
        string $appointmentId = self::APPOINTMENT_ID,
        array $addOns = [],
        ?DiscountInput $discount = null,
        string $paymentMethodId = self::CASH_METHOD_ID,
        int $amountCents = self::SERVICE_PRICE_CENTS,
        string $actorAccountId = self::ACTOR_ID,
    ): CreateAppointmentPaymentInput {
        return new CreateAppointmentPaymentInput(
            appointmentId: $appointmentId,
            addOns: $addOns,
            discount: $discount,
            paymentMethodId: $paymentMethodId,
            amountCents: $amountCents,
            actorAccountId: $actorAccountId,
        );
    }

    public static function recordInput(
        string $paymentId = self::PAYMENT_ID,
        string $paymentMethodId = self::CASH_METHOD_ID,
        int $amountCents = self::SERVICE_PRICE_CENTS,
        string $actorAccountId = self::ACTOR_ID,
    ): RecordPaymentTransactionInput {
        return new RecordPaymentTransactionInput($paymentId, $paymentMethodId, $amountCents, $actorAccountId);
    }

    public static function voidInput(
        string $paymentId = self::PAYMENT_ID,
        string $transactionId = self::TRANSACTION_ID,
        string $actorAccountId = self::ACTOR_ID,
    ): VoidPaymentTransactionInput {
        return new VoidPaymentTransactionInput($paymentId, $transactionId, $actorAccountId);
    }

    public static function idGenerator(int $addOns = 0, int $voids = 0): FixedIdGenerator
    {
        return new FixedIdGenerator(
            self::GENERATED_PAYMENT_ID,
            self::GENERATED_SERVICE_ITEM_ID,
            ...self::addOnIds($addOns),
            ...[self::GENERATED_TRANSACTION_ID],
            ...self::voidIds($voids),
        );
    }

    /**
     * @return list<string>
     */
    public static function voidIds(int $count): array
    {
        $ids = [];

        for ($index = 1; $index <= $count; $index++) {
            $ids[] = sprintf('01930000-0000-7000-8000-%012d', 3000 + $index);
        }

        return $ids;
    }

    /**
     * @return list<string>
     */
    public static function addOnIds(int $count): array
    {
        $ids = [];

        for ($index = 1; $index <= $count; $index++) {
            $ids[] = sprintf('01930000-0000-7000-8000-%012d', 2000 + $index);
        }

        return $ids;
    }

    /**
     * @return list<PaymentAddOnInput>
     */
    public static function addOns(int $count, int $amountCents = 1_000): array
    {
        $addOns = [];

        for ($index = 1; $index <= $count; $index++) {
            $addOns[] = new PaymentAddOnInput('Add-on '.$index, $amountCents);
        }

        return $addOns;
    }
}
