<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

use App\Shared\Contracts\DomainFailure;
use DomainException;

final class PageOutOfRange extends DomainException implements DomainFailure
{
    public static function forPage(int $page, int $maximum): self
    {
        return new self("A page has to fall between 1 and [{$maximum}], got [{$page}].");
    }

    public function errorCode(): string
    {
        return 'page_out_of_range';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
