<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Stripe;

use Stripe\Event;

final class StripeWebhookEvents
{
    private const SUBSCRIPTION_EVENTS = [
        Event::CUSTOMER_SUBSCRIPTION_CREATED,
        Event::CUSTOMER_SUBSCRIPTION_UPDATED,
        Event::CUSTOMER_SUBSCRIPTION_DELETED,
    ];

    private const INVOICE_EVENTS = [
        Event::INVOICE_PAID,
        Event::INVOICE_PAYMENT_FAILED,
    ];

    public function subscriptionIdOf(Event $event): ?string
    {
        $object = $event->data->object->toArray();

        if ($event->type === Event::CHECKOUT_SESSION_COMPLETED) {
            return self::idOf($object['subscription'] ?? null);
        }

        if (in_array($event->type, self::SUBSCRIPTION_EVENTS, true)) {
            return self::idOf($object['id'] ?? null);
        }

        if (in_array($event->type, self::INVOICE_EVENTS, true)) {
            return self::idOf($object['parent']['subscription_details']['subscription'] ?? null);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|string|null  $reference
     */
    private static function idOf(array|string|null $reference): ?string
    {
        if (is_array($reference)) {
            return self::idOf($reference['id'] ?? null);
        }

        return $reference === '' ? null : $reference;
    }
}
