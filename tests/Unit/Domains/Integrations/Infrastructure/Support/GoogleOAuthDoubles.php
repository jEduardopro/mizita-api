<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Integrations\Infrastructure\Support;

use App\Domains\Integrations\Infrastructure\Google\GoogleOAuthClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\GoogleProvider;
use Mockery;
use Mockery\MockInterface;

final class GoogleOAuthDoubles
{
    public const CLIENT_ID = 'mizita-client-id.apps.googleusercontent.com';

    public const CLIENT_SECRET = 'mizita-client-secret';

    public const REDIRECT_URI = 'https://mizita.test/integrations/google/callback';

    public const TIMEOUT_SECONDS = 10;

    public static function provider(): GoogleProvider&MockInterface
    {
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('stateless')->andReturnSelf()->byDefault();
        $provider->shouldReceive('setScopes')->andReturnSelf()->byDefault();

        return $provider;
    }

    public static function socialiteBuilding(GoogleProvider $provider): SocialiteManager&MockInterface
    {
        $socialite = Mockery::mock(SocialiteManager::class);
        $socialite->shouldReceive('buildProvider')->andReturn($provider)->byDefault();

        return $socialite;
    }

    public static function clientOver(GoogleProvider $provider): GoogleOAuthClient
    {
        return self::clientBuiltBy(self::socialiteBuilding($provider));
    }

    public static function clientBuiltBy(SocialiteManager $socialite): GoogleOAuthClient
    {
        return new GoogleOAuthClient(
            socialite: $socialite,
            clientId: self::CLIENT_ID,
            clientSecret: self::CLIENT_SECRET,
            redirectUri: self::REDIRECT_URI,
            timeoutSeconds: self::TIMEOUT_SECONDS,
        );
    }

    public static function tokenEndpointRejection(int $status, string $body): ClientException
    {
        return new ClientException(
            'Client error: POST https://oauth2.googleapis.com/token refresh_token='.IntegrationsFixtures::REFRESH_TOKEN.' resulted in '.$body,
            new Request('POST', 'https://oauth2.googleapis.com/token', [], 'refresh_token='.IntegrationsFixtures::REFRESH_TOKEN),
            new Response($status, ['Content-Type' => 'application/json'], $body),
        );
    }
}
