<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Eloquent\Mappers;

use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\Infrastructure\Eloquent\Models\CalendarConnectionModel;
use App\Domains\Integrations\ValueObjects\CalendarTokens;
use DateTimeImmutable;

final class CalendarConnectionMapper
{
    public function toEntity(CalendarConnectionModel $model, string $businessId): CalendarConnection
    {
        return CalendarConnection::restore(
            id: $model->uuid,
            businessId: $businessId,
            staffMemberId: $model->staffMember->uuid,
            provider: $model->provider,
            accountEmail: $model->account_email,
            externalCalendarId: $model->external_calendar_id,
            status: $model->status,
            connectedAt: $model->connected_at,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(CalendarConnection $connection, int $businessKey, int $staffMemberKey): array
    {
        return [
            'uuid' => $connection->id,
            'business_id' => $businessKey,
            'staff_member_id' => $staffMemberKey,
            'provider' => $connection->provider,
            'account_email' => $connection->accountEmail(),
            'external_calendar_id' => $connection->externalCalendarId(),
            'status' => $connection->status(),
            'connected_at' => $connection->connectedAt(),
        ];
    }

    /**
     * @return array{access_token: string, refresh_token: string, access_token_expires_at: DateTimeImmutable}
     */
    public function tokenAttributes(CalendarTokens $tokens): array
    {
        return [
            'access_token' => $tokens->accessToken,
            'refresh_token' => $tokens->refreshToken,
            'access_token_expires_at' => $tokens->accessTokenExpiresAt,
        ];
    }
}
