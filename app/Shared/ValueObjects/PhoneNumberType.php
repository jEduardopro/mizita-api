<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

/**
 * The platform's own vocabulary for a fact it does not compute itself. The
 * translation from the parsing library's type lives in LibPhoneNumberParser: a
 * value object every domain may import must never drag a vendor package into
 * the import graph of every entity in the repository.
 *
 * The backed values are what phones.number_type stores, so they are a storage
 * contract and must not be renamed once rows exist. They are deliberately not
 * constrained by the database, because a check constraint would turn a library
 * upgrade into a migration.
 */
enum PhoneNumberType: string
{
    case FixedLine = 'fixed_line';

    case Mobile = 'mobile';

    /** Several countries, the United States among them, publish one range for both. */
    case FixedLineOrMobile = 'fixed_line_or_mobile';

    case TollFree = 'toll_free';

    case PremiumRate = 'premium_rate';

    case SharedCost = 'shared_cost';

    case Voip = 'voip';

    case PersonalNumber = 'personal_number';

    case Pager = 'pager';

    /** Universal access, or company, numbers: one number routed to many offices. */
    case Uan = 'uan';

    /** The number is dialable but matches no published pattern for its region. */
    case Unknown = 'unknown';

    case Emergency = 'emergency';

    case Voicemail = 'voicemail';

    case ShortCode = 'short_code';

    case StandardRate = 'standard_rate';
}
