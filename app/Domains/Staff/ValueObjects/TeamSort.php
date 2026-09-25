<?php

declare(strict_types=1);

namespace App\Domains\Staff\ValueObjects;

enum TeamSort: string
{
    case Name = 'name';

    case CreatedAt = 'created_at';
}
