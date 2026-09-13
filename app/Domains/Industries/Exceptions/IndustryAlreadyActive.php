<?php

declare(strict_types=1);

namespace App\Domains\Industries\Exceptions;

use DomainException;

/**
 * Deliberately not a DomainFailure: the catalog has no write endpoint, so this
 * can only fire from a seeder or a console command - a programming error, not
 * something a user should be shown a translated sentence about.
 */
final class IndustryAlreadyActive extends DomainException
{
    public static function for(string $id): self
    {
        return new self("Industry [{$id}] is already active.");
    }
}
