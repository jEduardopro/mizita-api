<?php

declare(strict_types=1);

use App\Domains\Integrations\Exceptions\CalendarAuthorizationFailed;
use App\Domains\Integrations\Infrastructure\Google\GoogleAccessTokens;
use App\Domains\Integrations\Infrastructure\Google\GoogleApiFailure;
use App\Domains\Integrations\Infrastructure\Google\GoogleCalendarApi;
use App\Domains\Integrations\Infrastructure\Google\GoogleCalendarProvisioning;
use App\Domains\Integrations\ValueObjects\CalendarGrant;
use App\Domains\Integrations\ValueObjects\CalendarTokens;
use App\Shared\ValueObjects\DomainFailureKind;
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

    $tokens = new GoogleAccessTokens(GoogleOAuthDoubles::clientOver($this->provider), new FakeClock(IntegrationsFixtures::now()));

    $this->provisioning = new GoogleCalendarProvisioning(new GoogleCalendarApi($tokens, 10), $tokens, 10);

    $this->calendarsUrl = IntegrationsFixtures::CALENDAR_API.'/calendars';
    $this->calendarUrl = $this->calendarsUrl.'/'.IntegrationsFixtures::ENCODED_CALENDAR_ID;

    $this->sent = fn (): array => Http::recorded()
        ->map(fn (array $exchange): array => ['method' => $exchange[0]->method(), 'url' => $exchange[0]->url()])
        ->all();

    $this->failureOf = function (callable $action): ?Throwable {
        try {
            $action();
        } catch (Throwable $failure) {
            return $failure;
        }

        return null;
    };
});

afterEach(function () {
    $this->credentials->uninstall();
});

describe('creating the dedicated calendar', function () {
    it('creates a secondary calendar named after the business, in the business timezone', function () {
        Http::fakeSequence()->push(['id' => IntegrationsFixtures::CALENDAR_ID]);

        $this->provisioning->createCalendar(IntegrationsFixtures::grant(), IntegrationsFixtures::business());

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === $this->calendarsUrl
            && $request->data() === ['summary' => 'Mizita – Barbería Ñandú', 'timeZone' => 'Europe/Madrid']);
    });

    it('hands back the id Google gave the calendar', function () {
        Http::fakeSequence()->push(['id' => IntegrationsFixtures::CALENDAR_ID]);

        expect($this->provisioning->createCalendar(IntegrationsFixtures::grant(), IntegrationsFixtures::business()))
            ->toBe(IntegrationsFixtures::CALENDAR_ID);
    });

    it('acts with the freshly granted token, not with any stored credentials', function () {
        Http::fakeSequence()->push(['id' => IntegrationsFixtures::CALENDAR_ID]);

        $this->provisioning->createCalendar(IntegrationsFixtures::grant(), IntegrationsFixtures::business());

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer '.IntegrationsFixtures::GRANT_ACCESS_TOKEN));

        expect($this->credentials->selects)->toBe([]);
    });

    it('refuses the authorization when Google does not create the calendar', function (int $status, array $body) {
        Http::fakeSequence()->push($body, $status);

        $failure = ($this->failureOf)(fn () => $this->provisioning->createCalendar(IntegrationsFixtures::grant(), IntegrationsFixtures::business()));

        expect($failure)->toBeInstanceOf(CalendarAuthorizationFailed::class)
            ->and($failure->errorCode())->toBe('calendar_authorization_failed')
            ->and($failure->kind())->toBe(DomainFailureKind::Invalid);
    })->with([
        'a refusal' => [403, ['error' => ['code' => 403]]],
        'a server error' => [500, ['error' => ['code' => 500]]],
        'a success naming no calendar' => [200, []],
        'a success naming an empty calendar' => [200, ['id' => '']],
    ]);

    it('refuses the authorization when Google cannot be reached, without leaking the token', function () {
        Http::fakeSequence()->pushFailedConnection('timed out with Bearer '.IntegrationsFixtures::GRANT_ACCESS_TOKEN);

        $failure = ($this->failureOf)(fn () => $this->provisioning->createCalendar(IntegrationsFixtures::grant(), IntegrationsFixtures::business()));

        expect($failure)->toBeInstanceOf(CalendarAuthorizationFailed::class)
            ->and($failure->getMessage())->not->toContain(IntegrationsFixtures::GRANT_ACCESS_TOKEN)
            ->and($failure->getPrevious())->toBeNull();
    });
});

describe('adopting the calendar of an earlier connection', function () {
    it('keeps a calendar Google still has', function () {
        Http::fakeSequence()->push(['id' => IntegrationsFixtures::CALENDAR_ID]);

        expect($this->provisioning->adoptCalendar(IntegrationsFixtures::grant(), IntegrationsFixtures::CALENDAR_ID, IntegrationsFixtures::business()))
            ->toBe(IntegrationsFixtures::CALENDAR_ID)
            ->and(($this->sent)())->toBe([['method' => 'GET', 'url' => $this->calendarUrl]]);
    });

    it('creates a new calendar when the old one is out of reach', function (int $status) {
        Http::fakeSequence()->pushStatus($status)->push(['id' => 'mizita-new@group.calendar.google.com']);

        expect($this->provisioning->adoptCalendar(IntegrationsFixtures::grant(), IntegrationsFixtures::CALENDAR_ID, IntegrationsFixtures::business()))
            ->toBe('mizita-new@group.calendar.google.com')
            ->and(($this->sent)())->toBe([
                ['method' => 'GET', 'url' => $this->calendarUrl],
                ['method' => 'POST', 'url' => $this->calendarsUrl],
            ]);
    })->with([403, 404, 410]);

    it('refuses the authorization when Google fails for another reason', function () {
        Http::fakeSequence()->pushStatus(500);

        expect(fn () => $this->provisioning->adoptCalendar(IntegrationsFixtures::grant(), IntegrationsFixtures::CALENDAR_ID, IntegrationsFixtures::business()))
            ->toThrow(CalendarAuthorizationFailed::class);

        Http::assertSentCount(1);
    });
});

describe('deleting the dedicated calendar', function () {
    it('deletes the connection calendar with the stored credentials', function () {
        Http::fakeSequence()->pushStatus(204);

        $this->provisioning->deleteCalendar(IntegrationsFixtures::connection());

        expect(($this->sent)())->toBe([['method' => 'DELETE', 'url' => $this->calendarUrl]]);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer '.IntegrationsFixtures::ACCESS_TOKEN));
    });

    it('treats a calendar already gone as deleted', function (int $status) {
        Http::fakeSequence()->pushStatus($status);

        expect(($this->failureOf)(fn () => $this->provisioning->deleteCalendar(IntegrationsFixtures::connection())))->toBeNull();
    })->with([404, 410]);

    it('gives up quietly once the authorization was revoked', function () {
        $this->provider->shouldReceive('refreshToken')->once()
            ->andReturn(new Token(IntegrationsFixtures::REFRESHED_ACCESS_TOKEN, '', 3600, []));
        Http::fakeSequence()->pushStatus(401)->pushStatus(401);

        expect(($this->failureOf)(fn () => $this->provisioning->deleteCalendar(IntegrationsFixtures::connection())))->toBeNull();
    });

    it('fails with a Google failure for any other refusal', function () {
        Http::fakeSequence()->pushStatus(500);

        $failure = ($this->failureOf)(fn () => $this->provisioning->deleteCalendar(IntegrationsFixtures::connection()));

        expect($failure)->toBeInstanceOf(GoogleApiFailure::class)
            ->and($failure->getMessage())->toContain('DELETE')
            ->and($failure->getMessage())->toContain('HTTP 500');
    });
});

describe('revoking the authorization', function () {
    it('posts the stored refresh token to the revocation endpoint as a form', function () {
        Http::fakeSequence()->push([]);

        $this->provisioning->revokeAuthorization(IntegrationsFixtures::connection());

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://oauth2.googleapis.com/revoke'
            && $request->isForm()
            && $request->data() === ['token' => IntegrationsFixtures::REFRESH_TOKEN]);
    });

    it('treats a token Google already forgot as revoked', function () {
        Http::fakeSequence()->push(['error' => 'invalid_token'], 400);

        expect(($this->failureOf)(fn () => $this->provisioning->revokeAuthorization(IntegrationsFixtures::connection())))->toBeNull();
    });

    it('fails with a Google failure that never carries the refresh token when Google errs', function () {
        Http::fakeSequence()->push(['error' => 'backend_error'], 503);

        $failure = ($this->failureOf)(fn () => $this->provisioning->revokeAuthorization(IntegrationsFixtures::connection()));

        expect($failure)->toBeInstanceOf(GoogleApiFailure::class)
            ->and($failure->getMessage())->toContain('HTTP 503')
            ->and($failure->getMessage())->not->toContain(IntegrationsFixtures::REFRESH_TOKEN);
    });

    it('fails with a Google failure that never carries the refresh token when Google is out of reach', function () {
        Http::fakeSequence()->pushFailedConnection('timed out posting token='.IntegrationsFixtures::REFRESH_TOKEN);

        $failure = ($this->failureOf)(fn () => $this->provisioning->revokeAuthorization(IntegrationsFixtures::connection()));

        expect($failure)->toBeInstanceOf(GoogleApiFailure::class)
            ->and($failure->getMessage())->not->toContain(IntegrationsFixtures::REFRESH_TOKEN)
            ->and($failure->getPrevious())->toBeNull();
    });

    it('revokes the credentials of a connection already soft deleted', function () {
        $this->credentials->hold(connectionId: IntegrationsFixtures::OTHER_CONNECTION_ID, refreshToken: '1//archived-refresh-token', deletedAt: '2026-03-29 09:00:00');
        Http::fakeSequence()->push([]);

        $this->provisioning->revokeAuthorization(IntegrationsFixtures::connection(id: IntegrationsFixtures::OTHER_CONNECTION_ID));

        Http::assertSent(fn (Request $request): bool => $request->data() === ['token' => '1//archived-refresh-token']);
    });
});

describe('discarding a calendar created for an abandoned grant', function () {
    it('deletes the calendar with the granted token and nothing else', function () {
        Http::fakeSequence()->pushStatus(204);

        $this->provisioning->discardCalendar(IntegrationsFixtures::grant(), IntegrationsFixtures::CALENDAR_ID);

        expect(($this->sent)())->toBe([['method' => 'DELETE', 'url' => $this->calendarUrl]]);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer '.IntegrationsFixtures::GRANT_ACCESS_TOKEN)
            && $request->hasHeader('Accept', 'application/json')
            && $request->body() === '');
    });

    it('url-encodes the calendar id into a single path segment', function () {
        Http::fakeSequence()->pushStatus(204);

        $this->provisioning->discardCalendar(IntegrationsFixtures::grant(), 'Ñandú team/2026 #1@group.calendar.google.com');

        expect(($this->sent)())->toBe([[
            'method' => 'DELETE',
            'url' => $this->calendarsUrl.'/%C3%91and%C3%BA%20team%2F2026%20%231%40group.calendar.google.com',
        ]]);
    });

    it('never reads the stored credentials', function () {
        Http::fakeSequence()->pushStatus(204);

        $this->provisioning->discardCalendar(IntegrationsFixtures::grant(), IntegrationsFixtures::CALENDAR_ID);

        Http::assertNotSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer '.IntegrationsFixtures::ACCESS_TOKEN));

        expect($this->credentials->selects)->toBe([]);
    });

    it('treats the calendar as discarded when Google answers', function (int $status) {
        Http::fakeSequence()->pushStatus($status);

        expect(($this->failureOf)(fn () => $this->provisioning->discardCalendar(IntegrationsFixtures::grant(), IntegrationsFixtures::CALENDAR_ID)))
            ->toBeNull();

        Http::assertSentCount(1);
    })->with([
        'ok' => 200,
        'no content' => 204,
        'not found' => 404,
        'gone' => 410,
    ]);

    it('fails with a Google failure naming the status for any other answer', function (int $status) {
        Http::fakeSequence()->push(['error' => ['code' => $status, 'message' => 'Bearer '.IntegrationsFixtures::GRANT_ACCESS_TOKEN]], $status);

        $failure = ($this->failureOf)(fn () => $this->provisioning->discardCalendar(IntegrationsFixtures::grant(), IntegrationsFixtures::CALENDAR_ID));

        expect($failure)->toBeInstanceOf(GoogleApiFailure::class)
            ->and($failure->getMessage())->toBe('Google answered DELETE /calendars/'.IntegrationsFixtures::ENCODED_CALENDAR_ID." with HTTP {$status}.")
            ->and($failure->getMessage())->not->toContain(IntegrationsFixtures::GRANT_ACCESS_TOKEN)
            ->and($failure->getPrevious())->toBeNull();
    })->with([
        'unauthorized' => 401,
        'forbidden' => 403,
        'server error' => 500,
        'unavailable' => 503,
    ]);

    it('neither refreshes nor retries when the granted token is refused', function () {
        $this->provider->shouldNotReceive('refreshToken');
        Http::fakeSequence()->pushStatus(401);

        ($this->failureOf)(fn () => $this->provisioning->discardCalendar(IntegrationsFixtures::grant(), IntegrationsFixtures::CALENDAR_ID));

        Http::assertSentCount(1);

        expect($this->credentials->selects)->toBe([]);
    });

    it('fails with a Google failure that never carries the token when Google is out of reach', function () {
        Http::fakeSequence()->pushFailedConnection('timed out with Bearer '.IntegrationsFixtures::GRANT_ACCESS_TOKEN);

        $failure = ($this->failureOf)(fn () => $this->provisioning->discardCalendar(IntegrationsFixtures::grant(), IntegrationsFixtures::CALENDAR_ID));

        expect($failure)->toBeInstanceOf(GoogleApiFailure::class)
            ->and($failure->getMessage())->toStartWith('Google did not answer DELETE /calendars/'.IntegrationsFixtures::ENCODED_CALENDAR_ID)
            ->and($failure->getMessage())->not->toContain(IntegrationsFixtures::GRANT_ACCESS_TOKEN)
            ->and($failure->getPrevious())->toBeNull();
    });
});

describe('revoking an abandoned grant', function () {
    beforeEach(function () {
        $this->grantedRefreshToken = '1//granted-refresh-token-secret';

        $this->abandonedGrant = new CalendarGrant(
            accountEmail: IntegrationsFixtures::ACCOUNT_EMAIL,
            tokens: new CalendarTokens(
                accessToken: IntegrationsFixtures::GRANT_ACCESS_TOKEN,
                refreshToken: $this->grantedRefreshToken,
                accessTokenExpiresAt: new DateTimeImmutable('2026-03-29T11:00:00+00:00'),
            ),
        );
    });

    it('posts the granted refresh token to the revocation endpoint as a form', function () {
        Http::fakeSequence()->push([]);

        $this->provisioning->revokeGrant($this->abandonedGrant);

        expect(($this->sent)())->toBe([['method' => 'POST', 'url' => 'https://oauth2.googleapis.com/revoke']]);

        Http::assertSent(fn (Request $request): bool => $request->isForm()
            && $request->data() === ['token' => $this->grantedRefreshToken]
            && ! $request->hasHeader('Authorization'));
    });

    it('never reads the stored credentials', function () {
        Http::fakeSequence()->push([]);

        $this->provisioning->revokeGrant($this->abandonedGrant);

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->body(), rawurlencode(IntegrationsFixtures::REFRESH_TOKEN)));

        expect($this->credentials->selects)->toBe([]);
    });

    it('treats the grant as revoked when Google answers', function (int $status) {
        Http::fakeSequence()->push(['error' => 'invalid_token'], $status);

        expect(($this->failureOf)(fn () => $this->provisioning->revokeGrant($this->abandonedGrant)))->toBeNull();

        Http::assertSentCount(1);
    })->with([
        'ok' => 200,
        'no content' => 204,
        'a token Google already forgot' => 400,
    ]);

    it('fails with a Google failure naming the status for any other answer', function (int $status) {
        Http::fakeSequence()->push(['error' => 'token='.$this->grantedRefreshToken], $status);

        $failure = ($this->failureOf)(fn () => $this->provisioning->revokeGrant($this->abandonedGrant));

        expect($failure)->toBeInstanceOf(GoogleApiFailure::class)
            ->and($failure->getMessage())->toBe("Google answered POST https://oauth2.googleapis.com/revoke with HTTP {$status}.")
            ->and($failure->getMessage())->not->toContain($this->grantedRefreshToken)
            ->and($failure->getPrevious())->toBeNull();
    })->with([
        'unauthorized' => 401,
        'forbidden' => 403,
        'server error' => 500,
        'unavailable' => 503,
    ]);

    it('fails with a Google failure that never carries the refresh token when Google is out of reach', function () {
        Http::fakeSequence()->pushFailedConnection('timed out posting token='.$this->grantedRefreshToken);

        $failure = ($this->failureOf)(fn () => $this->provisioning->revokeGrant($this->abandonedGrant));

        expect($failure)->toBeInstanceOf(GoogleApiFailure::class)
            ->and($failure->getMessage())->toStartWith('Google did not answer POST https://oauth2.googleapis.com/revoke')
            ->and($failure->getMessage())->not->toContain($this->grantedRefreshToken)
            ->and($failure->getPrevious())->toBeNull();
    });
});
