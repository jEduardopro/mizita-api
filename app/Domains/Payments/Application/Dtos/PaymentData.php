<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\Dtos;

use App\Domains\Payments\Entities\Payment;
use App\Domains\Payments\Entities\PaymentItem;
use App\Domains\Payments\ValueObjects\PaymentStatus;
use DateTimeImmutable;

final readonly class PaymentData
{
    /**
     * @param  list<PaymentItemData>  $items
     * @param  list<PaymentTransactionData>  $transactions
     */
    public function __construct(
        public string $id,
        public string $appointmentId,
        public string $currencyCode,
        public array $items,
        public int $subtotalCents,
        public int $discountAmountCents,
        public int $totalCents,
        public int $paidCents,
        public int $balanceCents,
        public PaymentStatus $status,
        public array $transactions,
        public DateTimeImmutable $createdAt,
    ) {}

    /**
     * @param  list<PaymentTransactionData>  $transactions
     */
    public static function fromEntity(Payment $payment, array $transactions): self
    {
        return new self(
            id: $payment->id,
            appointmentId: $payment->appointmentId,
            currencyCode: $payment->currency()->value,
            items: self::itemsOf($payment),
            subtotalCents: $payment->subtotal()->amount,
            discountAmountCents: $payment->discountAmount()->amount,
            totalCents: $payment->total()->amount,
            paidCents: $payment->paid()->amount,
            balanceCents: $payment->balance()->amount,
            status: $payment->status(),
            transactions: $transactions,
            createdAt: $payment->createdAt,
        );
    }

    /**
     * @return list<PaymentItemData>
     */
    private static function itemsOf(Payment $payment): array
    {
        return array_map(
            static fn (PaymentItem $item): PaymentItemData => PaymentItemData::fromItem($item),
            $payment->items(),
        );
    }
}
