<?php

declare(strict_types=1);

namespace App\Domains\Phones\Application\Dtos;

use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Shared\ValueObjects\PhoneNumber;

/**
 * Input boundary. The caller is the owner's own domain, which already knows
 * the owner's uuid and has already turned request strings into a PhoneNumber.
 */
final readonly class AttachPhoneInput
{
    public function __construct(
        public PhoneOwnerType $ownerType,
        public string $ownerId,
        public PhoneNumber $number,
    ) {}
}
