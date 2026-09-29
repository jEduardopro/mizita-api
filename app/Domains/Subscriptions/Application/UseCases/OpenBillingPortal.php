<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Application\UseCases;

use App\Domains\Subscriptions\Application\Dtos\BillingPortalData;
use App\Domains\Subscriptions\Application\Dtos\OpenBillingPortalInput;
use App\Domains\Subscriptions\Contracts\BillingPortal;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Exceptions\SubscriptionNotFound;
use App\Shared\Application\UseCaseResponse;

final class OpenBillingPortal
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
        private readonly BillingPortal $portal,
        private readonly string $returnUrl,
    ) {}

    /**
     * @return UseCaseResponse<BillingPortalData>
     */
    public function handle(OpenBillingPortalInput $input): UseCaseResponse
    {
        $subscription = $this->subscriptions->forBusiness($input->businessId);

        if ($subscription === null) {
            return UseCaseResponse::failure(SubscriptionNotFound::forBusiness($input->businessId));
        }

        return UseCaseResponse::success(new BillingPortalData(
            $this->portal->urlFor($subscription->billingCustomerId, $this->returnUrl),
        ));
    }
}
