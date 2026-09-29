<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Stripe;

use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response;

final class StripeApiFailure extends RuntimeException
{
    public static function requestFailed(string $operation, ApiErrorException $cause): self
    {
        return new self("Stripe refused or did not answer {$operation} ({$cause->getMessage()}).", 0, $cause);
    }

    public function concernsMissingResource(): bool
    {
        $cause = $this->getPrevious();

        return $cause instanceof ApiErrorException && $cause->getHttpStatus() === Response::HTTP_NOT_FOUND;
    }
}
