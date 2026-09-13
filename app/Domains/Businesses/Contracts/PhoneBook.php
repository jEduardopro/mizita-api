<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

use App\Shared\ValueObjects\PhoneNumber;

/**
 * Files a contact number against a business.
 *
 * Phone numbers are their own domain because every kind of record on the
 * platform eventually grows one. This port names PhoneNumber because that value
 * object is part of the shared kernel, not of the neighbour - a port may speak
 * the shared vocabulary without coupling the two domains.
 *
 * Write only: onboarding puts a number on file and never reads one back.
 */
interface PhoneBook
{
    public function attachToBusiness(string $businessId, PhoneNumber $phone): void;
}
