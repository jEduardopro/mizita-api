<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidContactFieldRequirement extends DomainException implements DomainFailure
{
    public static function forField(string $field, string $value): self
    {
        return new self('The contact field ['.$field.'] cannot be set to ['.$value.'].');
    }

    public function errorCode(): string
    {
        return 'invalid_contact_field_requirement';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
