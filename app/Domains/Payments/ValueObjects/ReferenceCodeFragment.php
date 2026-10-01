<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

use App\Domains\Payments\Exceptions\InvalidPaymentReportFilter;

final readonly class ReferenceCodeFragment
{
    public const MAXIMUM_LENGTH = 8;

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidPaymentReportFilter
     */
    public static function of(?string $value): ?self
    {
        $fragment = mb_strtoupper(trim((string) $value));

        if ($fragment === '') {
            return null;
        }

        if (mb_strlen($fragment) > self::MAXIMUM_LENGTH) {
            throw InvalidPaymentReportFilter::referenceTooLong(self::MAXIMUM_LENGTH);
        }

        return new self($fragment);
    }
}
