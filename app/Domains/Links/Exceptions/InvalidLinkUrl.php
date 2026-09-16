<?php

declare(strict_types=1);

namespace App\Domains\Links\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidLinkUrl extends DomainException implements DomainFailure
{
    public static function empty(): self
    {
        return new self('A link url cannot be empty.');
    }

    public static function malformed(): self
    {
        return new self('The url offered is not a readable web address.');
    }

    public static function unsupportedScheme(): self
    {
        return new self('A link url must be served over http or https.');
    }

    public static function tooLong(): self
    {
        return new self('The url offered is longer than a link url may be.');
    }

    public function errorCode(): string
    {
        return 'invalid_link_url';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
