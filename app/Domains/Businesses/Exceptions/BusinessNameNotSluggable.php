<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

/**
 * Nothing usable survived turning this name into a web address.
 *
 * A name written entirely in a script the slug alphabet does not cover, or made
 * only of punctuation, leaves an empty string behind. A business with no
 * address cannot be published, so onboarding stops here instead of inventing
 * one the owner never chose.
 */
final class BusinessNameNotSluggable extends DomainException implements DomainFailure
{
    public static function forName(string $name): self
    {
        return new self("The name [{$name}] does not produce a usable slug.");
    }

    public function errorCode(): string
    {
        return 'business_name_not_sluggable';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
