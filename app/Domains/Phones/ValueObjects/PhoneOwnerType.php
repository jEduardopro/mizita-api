<?php

declare(strict_types=1);

namespace App\Domains\Phones\ValueObjects;

/**
 * What a phone number belongs to.
 *
 * These values are the morph aliases written into phones.phoneable_type, and
 * that is the contract between this domain and the application's morph map:
 * every owning domain registers its model under the alias of its case here,
 * with Relation::enforceMorphMap in its own service provider. Phones itself
 * registers nothing - it only ever reads phoneable_type as an opaque string,
 * which is what keeps it from importing the domains that own the rows.
 *
 * An enum rather than a free string, so the column can never hold a value no
 * owning domain answers to.
 */
enum PhoneOwnerType: string
{
    case Business = 'business';

    case StaffMember = 'staff_member';

    case Customer = 'customer';
}
