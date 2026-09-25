<?php

declare(strict_types=1);

namespace App\Domains\Integrations\ValueObjects;

enum ExternalEventOrigin: string
{
    case PublishedByMizita = 'published_by_mizita';
    case AddedByHand = 'added_by_hand';
}
