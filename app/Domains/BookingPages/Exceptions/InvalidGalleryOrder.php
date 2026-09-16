<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidGalleryOrder extends DomainException implements DomainFailure
{
    public static function incomplete(): self
    {
        return new self('A gallery order must list every image in the gallery exactly once.');
    }

    public function errorCode(): string
    {
        return 'invalid_gallery_order';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
