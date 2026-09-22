<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Http\Resources;

final class PaymentMethodLabel
{
    private const TRANSLATION_PREFIX = 'payment_methods.';

    private const TRANSLATION_SUFFIX = '.label';

    public static function for(string $code): string
    {
        return (string) __(self::TRANSLATION_PREFIX.$code.self::TRANSLATION_SUFFIX);
    }
}
