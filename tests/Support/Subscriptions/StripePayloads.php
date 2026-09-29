<?php

declare(strict_types=1);

namespace Tests\Support\Subscriptions;

final class StripePayloads
{
    public const STARTED_AT = 1780326000;

    public const PERIOD_ENDS_AT = 1782918000;

    public const SESSION_ID = 'cs_test_Session0000000000000001';

    public const CLIENT_SECRET = 'cs_test_Session0000000000000001_secret_Test';

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function subscription(array $overrides = []): array
    {
        return [
            'id' => SubscriptionFixtures::BILLING_SUBSCRIPTION_ID,
            'object' => 'subscription',
            'customer' => SubscriptionFixtures::BILLING_CUSTOMER_ID,
            'status' => 'active',
            'start_date' => self::STARTED_AT,
            'canceled_at' => null,
            'cancel_at' => null,
            'cancel_at_period_end' => false,
            'items' => [
                'object' => 'list',
                'data' => [
                    [
                        'id' => 'si_Test0000000000000001',
                        'object' => 'subscription_item',
                        'current_period_end' => self::PERIOD_ENDS_AT,
                    ],
                ],
            ],
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function checkoutSession(array $overrides = []): array
    {
        return [
            'id' => self::SESSION_ID,
            'object' => 'checkout.session',
            'client_secret' => self::CLIENT_SECRET,
            'customer' => SubscriptionFixtures::BILLING_CUSTOMER_ID,
            'subscription' => SubscriptionFixtures::BILLING_SUBSCRIPTION_ID,
            ...$overrides,
        ];
    }
}
