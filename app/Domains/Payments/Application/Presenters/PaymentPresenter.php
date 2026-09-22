<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\Presenters;

use App\Domains\Payments\Application\Dtos\PaymentData;
use App\Domains\Payments\Application\Dtos\PaymentTransactionData;
use App\Domains\Payments\Contracts\PaymentMethodCatalog;
use App\Domains\Payments\Entities\Payment;
use App\Domains\Payments\Entities\PaymentMethod;
use App\Domains\Payments\Entities\PaymentTransaction;
use App\Domains\Payments\Exceptions\PaymentMethodNotFound;

final class PaymentPresenter
{
    public function __construct(
        private readonly PaymentMethodCatalog $paymentMethods,
    ) {}

    public function describe(string $businessId, Payment $payment): PaymentData
    {
        return PaymentData::fromEntity($payment, $this->describeTransactions($payment->transactions()));
    }

    /**
     * @param  list<PaymentTransaction>  $transactions
     * @return list<PaymentTransactionData>
     */
    private function describeTransactions(array $transactions): array
    {
        if ($transactions === []) {
            return [];
        }

        $methods = $this->paymentMethods->describeMany(self::paymentMethodIdsOf($transactions));

        return array_map(
            static fn (PaymentTransaction $transaction): PaymentTransactionData => PaymentTransactionData::fromTransaction(
                $transaction,
                self::paymentMethodOf($transaction, $methods),
            ),
            $transactions,
        );
    }

    /**
     * @param  list<PaymentTransaction>  $transactions
     * @return list<string>
     */
    private static function paymentMethodIdsOf(array $transactions): array
    {
        return array_values(array_unique(array_map(
            static fn (PaymentTransaction $transaction): string => $transaction->paymentMethodId,
            $transactions,
        )));
    }

    /**
     * @param  array<string, PaymentMethod>  $methods
     *
     * @throws PaymentMethodNotFound
     */
    private static function paymentMethodOf(PaymentTransaction $transaction, array $methods): PaymentMethod
    {
        return $methods[$transaction->paymentMethodId]
            ?? throw PaymentMethodNotFound::withId($transaction->paymentMethodId);
    }
}
