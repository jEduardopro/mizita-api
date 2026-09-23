<?php

declare(strict_types=1);

use App\Domains\Payments\ValueObjects\DiscountType;
use App\Domains\Payments\ValueObjects\PaymentMethodCode;
use App\Domains\Payments\ValueObjects\PaymentStatus;
use App\Domains\Payments\ValueObjects\PaymentTransactionType;

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
