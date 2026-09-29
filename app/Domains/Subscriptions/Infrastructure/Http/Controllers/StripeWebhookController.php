<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Http\Controllers;

use App\Domains\Subscriptions\Contracts\SubscriptionSyncQueue;
use App\Domains\Subscriptions\Infrastructure\Stripe\StripeWebhookEvents;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SensitiveParameter;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class StripeWebhookController extends Controller
{
    private const SIGNATURE_HEADER = 'Stripe-Signature';

    public function __construct(
        #[SensitiveParameter] private readonly string $webhookSecret,
    ) {}

    public function store(
        Request $request,
        StripeWebhookEvents $events,
        SubscriptionSyncQueue $syncQueue,
        ApiResponder $responder,
    ): JsonResponse {
        $event = $this->verifiedEventOf($request);

        if ($event === null) {
            return response()->json(['received' => false], Response::HTTP_BAD_REQUEST);
        }

        try {
            $billingSubscriptionId = $events->subscriptionIdOf($event);

            if ($billingSubscriptionId !== null) {
                $syncQueue->schedule($billingSubscriptionId);
            }

            return response()->json(['received' => true], Response::HTTP_OK);
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    private function verifiedEventOf(Request $request): ?Event
    {
        try {
            return Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header(self::SIGNATURE_HEADER, ''),
                $this->webhookSecret,
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return null;
        }
    }
}
