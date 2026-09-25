<?php

declare(strict_types=1);

namespace App\Domains\Integrations\ValueObjects;

enum ConnectionStatus: string
{
    case Connected = 'connected';
    case NeedsReconnect = 'needs_reconnect';
}
