<?php

declare(strict_types=1);

use App\Domains\Integrations\Exceptions\CalendarAuthorizationRevoked;
use App\Domains\Integrations\Infrastructure\Google\GoogleAccessTokens;
use App\Domains\Integrations\Infrastructure\Google\GoogleApiFailure;
use App\Domains\Integrations\Infrastructure\Google\GoogleCalendarApi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Two\Token;
use Tests\Support\FakeClock;
use Tests\TestCase;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\GoogleOAuthDoubles;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\IntegrationsFixtures;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\StoredCalendarCredentials;

uses(TestCase::class);

beforeEach(function () {
    Http::preventStrayRequests();

    $this->credentials = StoredCalendarCredentials::install()->hold();
    $this->provider = GoogleOAuthDoubles::provider();
    $this->api = new GoogleCalendarApi(
        new GoogleAccessTokens(GoogleOAuthDoubles::clientOver($this->provider), new FakeClock(IntegrationsFixtures::now())),
        3,
    );

    $this->eventsPath = GoogleCalendarApi::calendarPath(IntegrationsFixtures::CALENDAR_ID).'/events';

    $this->sentBearers = fn (): array => Http::recorded()
        ->map(fn (array $exchange): string => $exchange[0]->header('Authorization')[0] ?? '')
        ->all();
});

afterEach(function () {
    $this->credentials->uninstall();
});

describe('a request on behalf of a connection', function () {
    it('calls the Calendar API with the stored access token as a bearer', function () {
        Http::fakeSequence()->push(['items' => []]);

        $this->api->forConnection(IntegrationsFixtures::CONNECTION_ID, 'GET', $this->eventsPath);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === IntegrationsFixtures::CALENDAR_API.'/calendars/'.IntegrationsFixtures::ENCODED_CALENDAR_ID.'/events'
            && $request->hasHeader('Authorization', 'Bearer '.IntegrationsFixtures::ACCESS_TOKEN)
            && $request->hasHeader('Accept', 'application/json'));
    });

    it('hands back an unsuccessful answer untouched for the caller to judge', function () {
        Http::fakeSequence()->push(['error' => ['code' => 404]], 404);

        expect($this->api->forConnection(IntegrationsFixtures::CONNECTION_ID, 'GET', $this->eventsPath)->status())->toBe(404);
    });

    it('sends the options it was given', function () {
        Http::fakeSequence()->push(['id' => 'evt-1']);

        $this->api->forConnection(IntegrationsFixtures::CONNECTION_ID, 'POST', $this->eventsPath, ['json' => ['summary' => 'Corte']]);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->data() === ['summary' => 'Corte']);
    });
});

describe('an access token Google no longer accepts', function () {
    it('refreshes once and retries with the new token', function () {
        $this->provider->shouldReceive('refreshToken')->once()
            ->andReturn(new Token(IntegrationsFixtures::REFRESHED_ACCESS_TOKEN, '', 3600, []));
        Http::fakeSequence()->push([], 401)->push(['items' => []]);

        $response = $this->api->forConnection(IntegrationsFixtures::CONNECTION_ID, 'GET', $this->eventsPath);

        expect($response->status())->toBe(200)
            ->and(($this->sentBearers)())->toBe([
                'Bearer '.IntegrationsFixtures::ACCESS_TOKEN,
                'Bearer '.IntegrationsFixtures::REFRESHED_ACCESS_TOKEN,
            ]);
    });

    it('declares the authorization revoked when the refreshed token is refused too', function () {
        $this->provider->shouldReceive('refreshToken')->once()
            ->andReturn(new Token(IntegrationsFixtures::REFRESHED_ACCESS_TOKEN, '', 3600, []));
        Http::fakeSequence()->push([], 401)->push([], 401);

        expect(fn () => $this->api->forConnection(IntegrationsFixtures::CONNECTION_ID, 'GET', $this->eventsPath))
            ->toThrow(CalendarAuthorizationRevoked::class);

        Http::assertSentCount(2);
    });

    it('declares the authorization revoked when Google refuses the refresh itself', function () {
        $this->provider->shouldReceive('refreshToken')->once()
            ->andThrow(GoogleOAuthDoubles::tokenEndpointRejection(400, '{"error":"invalid_grant"}'));
        Http::fakeSequence()->push([], 401);

        expect(fn () => $this->api->forConnection(IntegrationsFixtures::CONNECTION_ID, 'GET', $this->eventsPath))
            ->toThrow(CalendarAuthorizationRevoked::class);

        Http::assertSentCount(1);
    });

    it('does not refresh for any other refusal', function (int $status) {
        $this->provider->shouldNotReceive('refreshToken');
        Http::fakeSequence()->push([], $status);

        expect($this->api->forConnection(IntegrationsFixtures::CONNECTION_ID, 'GET', $this->eventsPath)->status())->toBe($status);

        Http::assertSentCount(1);
    })->with([403, 404, 410, 500]);
});

describe('Google out of reach', function () {
    it('rethrows a dropped connection as a Google failure naming the call', function () {
        Http::fakeSequence()->pushFailedConnection('cURL error 28: Operation timed out');

        $failure = null;

        try {
            $this->api->forConnection(IntegrationsFixtures::CONNECTION_ID, 'GET', $this->eventsPath);
        } catch (GoogleApiFailure $unreachable) {
            $failure = $unreachable;
        }

        expect($failure)->toBeInstanceOf(GoogleApiFailure::class)
            ->and($failure?->getMessage())->toContain('GET '.$this->eventsPath);
    });

    it('never carries the access token in the message or the chain', function () {
        Http::fakeSequence()->pushFailedConnection('cURL error 28 with Authorization: Bearer '.IntegrationsFixtures::ACCESS_TOKEN);

        $failure = null;

        try {
            $this->api->forConnection(IntegrationsFixtures::CONNECTION_ID, 'GET', $this->eventsPath);
        } catch (GoogleApiFailure $unreachable) {
            $failure = $unreachable;
        }

        expect($failure?->getMessage())->not->toContain(IntegrationsFixtures::ACCESS_TOKEN)
            ->and($failure?->getPrevious())->toBeNull();
    });
});

describe('a request with a token the caller already holds', function () {
    it('sends the given token and never reads the stored credentials', function () {
        Http::fakeSequence()->push(['id' => 'cal-1']);

        $this->api->withAccessToken(IntegrationsFixtures::GRANT_ACCESS_TOKEN, 'POST', '/calendars', ['json' => ['summary' => 'Mizita']]);

        expect(($this->sentBearers)())->toBe(['Bearer '.IntegrationsFixtures::GRANT_ACCESS_TOKEN])
            ->and($this->credentials->selects)->toBe([]);
    });

    it('hands back a 401 untouched instead of refreshing a token it does not own', function () {
        $this->provider->shouldNotReceive('refreshToken');
        Http::fakeSequence()->push([], 401);

        expect($this->api->withAccessToken(IntegrationsFixtures::GRANT_ACCESS_TOKEN, 'GET', '/calendars/x')->status())->toBe(401);
    });
});

describe('the paths it builds', function () {
    it('encodes a calendar id as one path segment', function () {
        expect(GoogleCalendarApi::calendarPath(IntegrationsFixtures::CALENDAR_ID))
            ->toBe('/calendars/'.IntegrationsFixtures::ENCODED_CALENDAR_ID);
    });

    it('encodes an event id that would otherwise escape its segment', function () {
        expect(GoogleCalendarApi::eventPath(IntegrationsFixtures::CALENDAR_ID, '../evt 1?x'))
            ->toBe('/calendars/'.IntegrationsFixtures::ENCODED_CALENDAR_ID.'/events/..%2Fevt%201%3Fx');
    });
});
