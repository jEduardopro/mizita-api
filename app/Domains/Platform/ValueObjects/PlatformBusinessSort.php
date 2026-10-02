<?php

declare(strict_types=1);

namespace App\Domains\Platform\ValueObjects;

enum PlatformBusinessSort: string
{
    case CreatedAt = 'created_at';

    case Name = 'name';

    case ServicesCount = 'services_count';

    case CustomersCount = 'customers_count';
}
