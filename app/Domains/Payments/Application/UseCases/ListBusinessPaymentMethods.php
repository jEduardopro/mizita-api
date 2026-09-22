<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\UseCases;

use App\Domains\Payments\Application\Dtos\BusinessPaymentMethodData;
use App\Domains\Payments\Contracts\PaymentMethodCatalog;
use App\Domains\Payments\ValueObjects\AvailablePaymentMethod;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;

final class ListBusinessPaymentMethods
{
    public function __construct(
        private readonly PaymentMethodCatalog $paymentMethods,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<list<BusinessPaymentMethodData>>
     */
    public function handle(): UseCaseResponse
    {
        return UseCaseResponse::success(array_map(
            static fn (AvailablePaymentMethod $method): BusinessPaymentMethodData => BusinessPaymentMethodData::fromAvailable($method),
            $this->paymentMethods->availableFor($this->business->currentBusinessId()),
        ));
    }
}
