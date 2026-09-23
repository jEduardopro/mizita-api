<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class IncompleteContactFields extends DomainException implements DomainFailure
{
    /**
     * @param  list<string>  $keys
     */
    public static function missing(array $keys): self
    {
        return new self('The contact fields were submitted without ['.implode(', ', $keys).'].');
    }

    public function errorCode(): string
    {
        return 'incomplete_contact_fields';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
