<?php

declare(strict_types=1);

use App\Domains\Payments\ValueObjects\DiscountType;
use App\Domains\Payments\ValueObjects\PaymentMethodCode;
use App\Domains\Payments\ValueObjects\PaymentStatus;
use App\Domains\Payments\ValueObjects\PaymentTransactionType;
use App\Domains\Payments\ValueObjects\SaleSort;
use App\Domains\Payments\ValueObjects\TransactionSort;

it('backs every payment status with the string the column and the client share', function () {
    expect(PaymentStatus::Pending->value)->toBe('pending')
        ->and(PaymentStatus::PartiallyPaid->value)->toBe('partially_paid')
        ->and(PaymentStatus::Paid->value)->toBe('paid');
});

it('backs every transaction type with the string the column and the client share', function () {
    expect(PaymentTransactionType::Approved->value)->toBe('approved')
        ->and(PaymentTransactionType::Void->value)->toBe('void')
        ->and(PaymentTransactionType::Refund->value)->toBe('refund')
        ->and(PaymentTransactionType::Failed->value)->toBe('failed');
});

it('backs every discount type with the string the column and the client share', function () {
    expect(DiscountType::None->value)->toBe('none')
        ->and(DiscountType::Percentage->value)->toBe('percentage')
        ->and(DiscountType::Fixed->value)->toBe('fixed');
});

it('backs every payment method code with the string the catalogue is seeded with', function () {
    expect(PaymentMethodCode::Cash->value)->toBe('cash')
        ->and(PaymentMethodCode::BankTransfer->value)->toBe('bank_transfer');
});

it('holds the cases a stored value may be read back into and no others', function (string $enum, array $values) {
    expect(array_map(fn ($case) => $case->value, $enum::cases()))->toBe($values);
})->with([
    'payment status' => [PaymentStatus::class, ['pending', 'partially_paid', 'paid']],
    'transaction type' => [PaymentTransactionType::class, ['approved', 'void', 'refund', 'failed']],
    'discount type' => [DiscountType::class, ['none', 'percentage', 'fixed']],
    'payment method code' => [PaymentMethodCode::class, ['cash', 'bank_transfer']],
    'sale sort' => [SaleSort::class, ['created_at', 'total']],
    'transaction sort' => [TransactionSort::class, ['processed_at', 'amount']],
]);

it('reads a stored string back into the case that wrote it', function () {
    expect(PaymentStatus::from('partially_paid'))->toBe(PaymentStatus::PartiallyPaid)
        ->and(PaymentTransactionType::from('void'))->toBe(PaymentTransactionType::Void)
        ->and(DiscountType::from('percentage'))->toBe(DiscountType::Percentage)
        ->and(PaymentMethodCode::from('bank_transfer'))->toBe(PaymentMethodCode::BankTransfer);
});

describe('what each transaction type does to the money a payment holds', function () {
    it('counts only an approved charge towards what was collected', function (PaymentTransactionType $type, bool $counts) {
        expect($type->countsTowardsPaid())->toBe($counts);
    })->with([
        'approved' => [PaymentTransactionType::Approved, true],
        'void' => [PaymentTransactionType::Void, false],
        'refund' => [PaymentTransactionType::Refund, false],
        'failed' => [PaymentTransactionType::Failed, false],
    ]);

    it('takes a void and a refund back off what was collected', function (PaymentTransactionType $type, bool $reverses) {
        expect($type->reversesPaid())->toBe($reverses);
    })->with([
        'approved' => [PaymentTransactionType::Approved, false],
        'void' => [PaymentTransactionType::Void, true],
        'refund' => [PaymentTransactionType::Refund, true],
        'failed' => [PaymentTransactionType::Failed, false],
    ]);

    it('leaves a failed attempt out of both sides of the ledger', function () {
        expect(PaymentTransactionType::Failed->countsTowardsPaid())->toBeFalse()
            ->and(PaymentTransactionType::Failed->reversesPaid())->toBeFalse();
    });
});

describe('the sign a transaction type carries in a report', function () {
    it('reports only a void as money going back out', function (PaymentTransactionType $type, int $sign) {
        expect($type->sign())->toBe($sign);
    })->with([
        'approved' => [PaymentTransactionType::Approved, 1],
        'void' => [PaymentTransactionType::Void, -1],
        'refund' => [PaymentTransactionType::Refund, 1],
        'failed' => [PaymentTransactionType::Failed, 1],
    ]);

    it('signs an amount with the sign of its type', function (PaymentTransactionType $type, int $signed) {
        expect($type->signedAmount(2_500))->toBe($signed);
    })->with([
        'approved' => [PaymentTransactionType::Approved, 2_500],
        'void' => [PaymentTransactionType::Void, -2_500],
        'refund' => [PaymentTransactionType::Refund, 2_500],
        'failed' => [PaymentTransactionType::Failed, 2_500],
    ]);

    it('leaves a void of nothing at zero', function () {
        expect(PaymentTransactionType::Void->signedAmount(0))->toBe(0);
    });
});

describe('the status a sale is in', function () {
    it('reads the status off what was collected against what is owed', function (int $paid, int $total, PaymentStatus $status) {
        expect(PaymentStatus::forAmounts($paid, $total))->toBe($status);
    })->with([
        'nothing collected' => [0, 50_000, PaymentStatus::Pending],
        'one cent collected' => [1, 50_000, PaymentStatus::PartiallyPaid],
        'one cent short' => [49_999, 50_000, PaymentStatus::PartiallyPaid],
        'exactly what is owed' => [50_000, 50_000, PaymentStatus::Paid],
        'more than what is owed' => [50_001, 50_000, PaymentStatus::Paid],
        'a sale of nothing' => [0, 0, PaymentStatus::Paid],
    ]);
});
