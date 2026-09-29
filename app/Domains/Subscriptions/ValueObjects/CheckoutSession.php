<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\ValueObjects;

use SensitiveParameter;

final readonly class CheckoutSession
{
    public function __construct(
        public string $id,
        #[SensitiveParameter] public string $clientSecret,
    ) {}
}
