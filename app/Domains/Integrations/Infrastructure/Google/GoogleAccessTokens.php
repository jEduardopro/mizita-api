<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Google;

use App\Domains\Integrations\Exceptions\CalendarAuthorizationRevoked;
use App\Domains\Integrations\Infrastructure\Eloquent\Models\CalendarConnectionModel;
use App\Shared\Contracts\Clock;
use DateInterval;
use GuzzleHttp\Exception\ClientException;
use Throwable;

final class GoogleAccessTokens
{
    private const REFRESH_MARGIN = 'PT1M';

    private const REVOKED_GRANT = 'invalid_grant';

    private const BAD_REQUEST = 400;

    private const UNAUTHORIZED = 401;

    public function __construct(
        private readonly GoogleOAuthClient $oauth,
        private readonly Clock $clock,
    ) {}

    /**
     * @throws CalendarAuthorizationRevoked
     */
    public function current(string $connectionId): string
    {
        $model = $this->storedCredentialsOf($connectionId);
        $refreshAfter = $this->clock->now()->add(new DateInterval(self::REFRESH_MARGIN));

        if ($model->access_token_expires_at > $refreshAfter) {
            return (string) $model->access_token;
        }

        return $this->refreshStored($model);
    }

    /**
     * @throws CalendarAuthorizationRevoked
     */
    public function refreshed(string $connectionId): string
    {
        return $this->refreshStored($this->storedCredentialsOf($connectionId));
    }

    public function refreshTokenOf(string $connectionId): string
    {
        return (string) $this->storedCredentialsOf($connectionId)->refresh_token;
    }

    /**
     * @throws CalendarAuthorizationRevoked
     */
    private function refreshStored(CalendarConnectionModel $model): string
    {
        try {
            $token = $this->oauth->refresh((string) $model->refresh_token);
        } catch (ClientException $rejected) {
            throw $this->classify($model->uuid, $rejected);
        } catch (Throwable $failure) {
            throw GoogleApiFailure::tokenRefreshFailed($model->uuid, $failure::class);
        }

        $model->forceFill([
            'access_token' => $token->token,
            'refresh_token' => $token->refreshToken ?: $model->refresh_token,
            'access_token_expires_at' => $this->clock->now()->add(
                new DateInterval('PT'.max(0, (int) $token->expiresIn).'S'),
            ),
        ])->save();

        return (string) $token->token;
    }

    private function classify(string $connectionId, ClientException $rejected): CalendarAuthorizationRevoked|GoogleApiFailure
    {
        $status = $rejected->getResponse()->getStatusCode();
        $grantRevoked = $status === self::BAD_REQUEST
            && str_contains((string) $rejected->getResponse()->getBody(), self::REVOKED_GRANT);

        if ($status === self::UNAUTHORIZED || $grantRevoked) {
            return CalendarAuthorizationRevoked::forConnection($connectionId);
        }

        return GoogleApiFailure::tokenRefreshFailed($connectionId, 'HTTP '.$status);
    }

    private function storedCredentialsOf(string $connectionId): CalendarConnectionModel
    {
        return CalendarConnectionModel::query()->withTrashed()->where('uuid', $connectionId)->first()
            ?? throw GoogleApiFailure::unknownConnection($connectionId);
    }
}
