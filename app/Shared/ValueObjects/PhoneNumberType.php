<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

enum PhoneNumberType: string
{
    case FixedLine = 'fixed_line';

    case Mobile = 'mobile';

    case FixedLineOrMobile = 'fixed_line_or_mobile';

    case TollFree = 'toll_free';

    case PremiumRate = 'premium_rate';

    case SharedCost = 'shared_cost';

    case Voip = 'voip';

    case PersonalNumber = 'personal_number';

    case Pager = 'pager';

    case Uan = 'uan';

    case Unknown = 'unknown';

    case Emergency = 'emergency';

    case Voicemail = 'voicemail';

    case ShortCode = 'short_code';

    case StandardRate = 'standard_rate';
}
