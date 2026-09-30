<?php

declare(strict_types=1);

use App\Domains\Statistics\ValueObjects\CollectedTransactionType;

it('counts an approval as money in and a void or a refund as money out', function (CollectedTransactionType $type, int $sign) {
    expect($type->sign())->toBe($sign);
})->with([
    'approved' => [CollectedTransactionType::Approved, 1],
    'void' => [CollectedTransactionType::Void, -1],
    'refund' => [CollectedTransactionType::Refund, -1],
]);

it('selects exactly the transaction types that move money, leaving failed attempts out', function () {
    expect(CollectedTransactionType::values())->toBe(['approved', 'void', 'refund'])
        ->and(CollectedTransactionType::values())->not->toContain('failed');
});
