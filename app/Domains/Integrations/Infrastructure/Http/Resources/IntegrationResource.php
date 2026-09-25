<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Http\Resources;

use App\Domains\Integrations\Application\Dtos\CalendarConnectionData;
use App\Domains\Integrations\Application\Dtos\IntegrationData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read IntegrationData $resource
 */
final class IntegrationResource extends JsonResource
{
    /**
     * @return array{key: string, category: string, connection: array{id: string, status: string, account_email: string, connected_at: string}|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource->key->value,
            'category' => $this->resource->category->value,
            'connection' => self::connectionOf($this->resource->connection),
        ];
    }

    /**
     * @return array{id: string, status: string, account_email: string, connected_at: string}|null
     */
    private static function connectionOf(?CalendarConnectionData $connection): ?array
    {
        if ($connection === null) {
            return null;
        }

        return [
            'id' => $connection->id,
            'status' => $connection->status->value,
            'account_email' => $connection->accountEmail,
            'connected_at' => $connection->connectedAt->format(DATE_ATOM),
        ];
    }
}
