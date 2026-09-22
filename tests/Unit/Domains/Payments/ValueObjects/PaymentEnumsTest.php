<?php

declare(strict_types=1);

use App\Domains\Payments\ValueObjects\DiscountType;
use App\Domains\Payments\ValueObjects\PaymentMethodCode;
use App\Domains\Payments\ValueObjects\PaymentStatus;
use App\Domains\Payments\ValueObjects\PaymentTransactionStatus;

it('backs every payment status with the string the column and the client share', function () {
    expect(PaymentStatus::Pending->value)->toBe('pending')
        ->and(PaymentStatus::PartiallyPaid->value)->toBe('partially_paid')
        ->and(PaymentStatus::Paid->value)->toBe('paid');
});

it('backs every transaction status with the string the column and the client share', function () {
    expect(PaymentTransactionStatus::Completed->value)->toBe('completed')
        ->and(PaymentTransactionStatus::Voided->value)->toBe('voided');
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
    'transaction status' => [PaymentTransactionStatus::class, ['completed', 'voided']],
    'discount type' => [DiscountType::class, ['none', 'percentage', 'fixed']],
    'payment method code' => [PaymentMethodCode::class, ['cash', 'bank_transfer']],
]);

it('reads a stored string back into the case that wrote it', function () {
    expect(PaymentStatus::from('partially_paid'))->toBe(PaymentStatus::PartiallyPaid)
        ->and(PaymentTransactionStatus::from('voided'))->toBe(PaymentTransactionStatus::Voided)
        ->and(DiscountType::from('percentage'))->toBe(DiscountType::Percentage)
        ->and(PaymentMethodCode::from('bank_transfer'))->toBe(PaymentMethodCode::BankTransfer);
});
