<?php

declare(strict_types=1);

namespace App\Domains\Phones\ValueObjects;

/**
 * These values are the morph aliases written into phones.phoneable_type: every
 * owning domain registers its model under the alias of its case here, with
 * Relation::enforceMorphMap in its own provider. Phones registers nothing - it
 * reads phoneable_type as an opaque string, which is what keeps it from
 * importing the domains that own the rows.
 */
enum PhoneOwnerType: string
{
    case Business = 'business';

    case StaffMember = 'staff_member';

    case Customer = 'customer';
}
