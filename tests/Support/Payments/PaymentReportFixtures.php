<?php

declare(strict_types=1);

namespace Tests\Support\Payments;

use App\Domains\Payments\ValueObjects\PaymentStatus;
use App\Domains\Payments\ValueObjects\PaymentTransactionType;
use App\Domains\Payments\ValueObjects\SaleRecord;
use App\Domains\Payments\ValueObjects\TransactionRecord;
use DateTimeImmutable;

final class PaymentReportFixtures
{
    public const NEW_YORK = 'America/New_York';

    public const MADRID = 'Europe/Madrid';

    public const CUSTOMER_ID = '01930000-0000-7000-8000-00000000c0a1';

    public const SECOND_CUSTOMER_ID = '01930000-0000-7000-8000-00000000c0a2';

    public const CUSTOMER_NAME = 'José Ñúñez';

    public const SECOND_CUSTOMER_NAME = 'Grace Hopper';

    public const SALE_ID = '01930000-0000-7000-8000-000000005a01';

    public const SECOND_SALE_ID = '01930000-0000-7000-8000-000000005a02';

    public const REFERENCE_CODE = 'MZ7K2QP9';

    public const CREATED_AT = '2026-03-08T15:30:00+00:00';

    public const PROCESSED_AT = '2026-11-01T06:15:00+00:00';

    public static function sale(
        string $id = self::SALE_ID,
        string $customerId = self::CUSTOMER_ID,
        string $customerName = self::CUSTOMER_NAME,
        PaymentStatus $status = PaymentStatus::PartiallyPaid,
        int $totalCents = 65_000,
        string $currencyCode = PaymentFixtures::CURRENCY,
        string $referenceCode = self::REFERENCE_CODE,
        string $createdAt = self::CREATED_AT,
    ): SaleRecord {
        return new SaleRecord(
            id: $id,
            createdAt: new DateTimeImmutable($createdAt),
            customerId: $customerId,
            customerName: $customerName,
            status: $status,
            totalCents: $totalCents,
            currencyCode: $currencyCode,
            referenceCode: $referenceCode,
        );
    }

    public static function transaction(
        string $id = PaymentFixtures::TRANSACTION_ID,
        PaymentTransactionType $type = PaymentTransactionType::Approved,
        int $totalCents = 40_000,
        string $customerId = self::CUSTOMER_ID,
        string $customerName = self::CUSTOMER_NAME,
        string $currencyCode = PaymentFixtures::CURRENCY,
        string $paymentMethodCode = 'cash',
        string $processedAt = self::PROCESSED_AT,
    ): TransactionRecord {
        return new TransactionRecord(
            id: $id,
            processedAt: new DateTimeImmutable($processedAt),
            customerId: $customerId,
            customerName: $customerName,
            type: $type,
            totalCents: $totalCents,
            currencyCode: $currencyCode,
            paymentMethodCode: $paymentMethodCode,
        );
    }

    /**
     * @return list<string>
     */
    public static function customerIds(int $count): array
    {
        $ids = [];

        for ($index = 1; $index <= $count; $index++) {
            $ids[] = sprintf('01930000-0000-7000-8000-%012d', 700_000 + $index);
        }

        return $ids;
    }
}
