<?php

declare(strict_types=1);

use App\Domains\Integrations\Exceptions\CalendarAuthorizationRevoked;
use App\Domains\Integrations\Infrastructure\Google\GoogleAccessTokens;
use App\Domains\Integrations\Infrastructure\Google\GoogleApiFailure;
use App\Domains\Integrations\Infrastructure\Google\GoogleCalendarApi;
use App\Domains\Integrations\Infrastructure\Google\GoogleCalendarEventPublisher;
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
    $this->publisher = new GoogleCalendarEventPublisher(new GoogleCalendarApi(
        new GoogleAccessTokens(GoogleOAuthDoubles::clientOver($this->provider), new FakeClock(IntegrationsFixtures::now())),
        10,
    ));

    $this->eventsUrl = IntegrationsFixtures::CALENDAR_API.'/calendars/'.IntegrationsFixtures::ENCODED_CALENDAR_ID.'/events';

    $this->sent = fn (): array => Http::recorded()
        ->map(fn (array $exchange): array => [
            'method' => $exchange[0]->method(),
            'url' => strtok($exchange[0]->url(), '?'),
        ])
        ->all();

    $this->queryOf = function (Request $request): array {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query;
    };

    $this->publish = fn (?string $knownEventId = 'evt-known') => $this->publisher->publish(
        IntegrationsFixtures::connection(),
        $knownEventId,
        IntegrationsFixtures::draft(),
    );

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

describe('the event it writes', function () {
    beforeEach(function () {
        Http::fakeSequence()->push(['id' => 'evt-known']);

        ($this->publish)();

        $this->payload = Http::recorded()->first()[0]->data();
    });

    it('writes exactly the fields Mizita owns', function () {
        expect(array_keys($this->payload))
            ->toBe(['summary', 'description', 'status', 'start', 'end', 'extendedProperties']);
    });

    it('titles and describes the event with the draft', function () {
        expect($this->payload['summary'])->toBe('Corte de pelo · Ada Lovelace')
            ->and($this->payload['description'])->toBe("Referencia MZ-7Q2K\nhttps://mizita.test/calendar");
    });

    it('tags the event with the appointment uuid in a private extended property', function () {
        expect($this->payload['extendedProperties'])->toBe([
            'private' => ['mizita_appointment_id' => IntegrationsFixtures::APPOINTMENT_ID],
        ]);
    });

    it('places the event at its instants and in the business timezone', function () {
        expect($this->payload['start'])->toBe(['dateTime' => '2026-03-29T08:30:00+00:00', 'timeZone' => 'Europe/Madrid'])
            ->and($this->payload['end'])->toBe(['dateTime' => '2026-03-29T09:15:00+00:00', 'timeZone' => 'Europe/Madrid']);
    });

    it('always writes the event as confirmed', function () {
        expect($this->payload['status'])->toBe('confirmed');
    });

    it('invites nobody and carries no contact of the customer', function () {
        expect($this->payload)->not->toHaveKey('attendees')
            ->and(json_encode($this->payload, JSON_THROW_ON_ERROR))
            ->not->toContain('phone')
            ->not->toContain('email')
            ->not->toContain('@');
    });
});

describe('an event Mizita already published', function () {
    it('patches the known event and hands back its id', function () {
        Http::fakeSequence()->push(['id' => 'evt-known']);

        expect(($this->publish)())->toBe('evt-known')
            ->and(($this->sent)())->toBe([['method' => 'PATCH', 'url' => $this->eventsUrl.'/evt-known']]);
    });

    it('never looks the event up when the patch lands', function () {
        Http::fakeSequence()->push(['id' => 'evt-known']);

        ($this->publish)();

        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'PATCH');
    });

    it('inserts a fresh event when the known one is gone and nothing carries the tag', function (int $status) {
        Http::fakeSequence()
            ->push(['error' => ['code' => $status]], $status)
            ->push(['items' => []])
            ->push(['id' => 'evt-fresh']);

        expect(($this->publish)())->toBe('evt-fresh')
            ->and(($this->sent)())->toBe([
                ['method' => 'PATCH', 'url' => $this->eventsUrl.'/evt-known'],
                ['method' => 'GET', 'url' => $this->eventsUrl],
                ['method' => 'POST', 'url' => $this->eventsUrl],
            ]);
    })->with([404, 410]);

    it('looks the tagged event up when the known one is gone, before inserting a duplicate', function (int $status) {
        Http::fakeSequence()
            ->push(['error' => ['code' => $status]], $status)
            ->push(['items' => [['id' => 'evt-tagged', 'status' => 'confirmed']]])
            ->push(['id' => 'evt-tagged']);

        expect(($this->publish)())->toBe('evt-tagged')
            ->and(($this->sent)())->toBe([
                ['method' => 'PATCH', 'url' => $this->eventsUrl.'/evt-known'],
                ['method' => 'GET', 'url' => $this->eventsUrl],
                ['method' => 'PATCH', 'url' => $this->eventsUrl.'/evt-tagged'],
            ]);

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    })->with([404, 410]);

    it('looks the known-gone event up by its appointment tag, deleted events included', function (int $status) {
        Http::fakeSequence()
            ->push(['error' => ['code' => $status]], $status)
            ->push(['items' => []])
            ->push(['id' => 'evt-fresh']);

        ($this->publish)();

        $lookup = Http::recorded()->get(1)[0];

        expect($lookup->method())->toBe('GET')
            ->and(($this->queryOf)($lookup))->toBe([
                'privateExtendedProperty' => 'mizita_appointment_id='.IntegrationsFixtures::APPOINTMENT_ID,
                'showDeleted' => 'true',
                'maxResults' => '1',
            ]);
    })->with([404, 410]);

    it('restores a tagged event deleted in Google by patching it back as confirmed', function () {
        Http::fakeSequence()
            ->push(['error' => ['code' => 404]], 404)
            ->push(['items' => [['id' => 'evt-deleted', 'status' => 'cancelled']]])
            ->push(['id' => 'evt-deleted', 'status' => 'confirmed']);

        expect(($this->publish)())->toBe('evt-deleted');

        $restore = Http::recorded()->get(2)[0];

        expect($restore->method())->toBe('PATCH')
            ->and(strtok($restore->url(), '?'))->toBe($this->eventsUrl.'/evt-deleted')
            ->and($restore->data()['status'])->toBe('confirmed')
            ->and($restore->data()['extendedProperties'])->toBe([
                'private' => ['mizita_appointment_id' => IntegrationsFixtures::APPOINTMENT_ID],
            ]);
    });

    it('inserts a fresh event when the tagged one vanished between the lookup and its patch', function (int $status) {
        Http::fakeSequence()
            ->push(['error' => ['code' => 404]], 404)
            ->push(['items' => [['id' => 'evt-tagged']]])
            ->push(['error' => ['code' => $status]], $status)
            ->push(['id' => 'evt-fresh']);

        expect(($this->publish)())->toBe('evt-fresh')
            ->and(($this->sent)())->toBe([
                ['method' => 'PATCH', 'url' => $this->eventsUrl.'/evt-known'],
                ['method' => 'GET', 'url' => $this->eventsUrl],
                ['method' => 'PATCH', 'url' => $this->eventsUrl.'/evt-tagged'],
                ['method' => 'POST', 'url' => $this->eventsUrl],
            ]);
    })->with([404, 410]);

    it('fails with a Google failure and inserts nothing when the lookup after a gone event is refused', function () {
        Http::fakeSequence()
            ->push(['error' => ['code' => 404]], 404)
            ->push(['error' => ['code' => 500]], 500);

        $failure = ($this->failureOf)(fn () => ($this->publish)());

        expect($failure)->toBeInstanceOf(GoogleApiFailure::class)
            ->and($failure->getMessage())->toContain('GET')
            ->and($failure->getMessage())->toContain('HTTP 500')
            ->and($failure->getMessage())->not->toContain(IntegrationsFixtures::ACCESS_TOKEN);

        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    });

    it('fails with a Google failure and inserts nothing when the tagged patch is refused for another reason', function () {
        Http::fakeSequence()
            ->push(['error' => ['code' => 404]], 404)
            ->push(['items' => [['id' => 'evt-tagged']]])
            ->push(['error' => ['code' => 500]], 500);

        $failure = ($this->failureOf)(fn () => ($this->publish)());

        expect($failure)->toBeInstanceOf(GoogleApiFailure::class)
            ->and($failure->getMessage())->toContain('PATCH');

        Http::assertSentCount(3);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    });

    it('fails with a Google failure when the patch is refused for another reason', function () {
        Http::fakeSequence()->push(['error' => ['code' => 500]], 500);

        $failure = ($this->failureOf)(fn () => ($this->publish)());

        expect($failure)->toBeInstanceOf(GoogleApiFailure::class)
            ->and($failure->getMessage())->toContain('PATCH')
            ->and($failure->getMessage())->toContain('HTTP 500')
            ->and($failure->getMessage())->not->toContain(IntegrationsFixtures::ACCESS_TOKEN);

        Http::assertSentCount(1);
    });

    it('fails with a Google failure when a successful patch names no event', function () {
        Http::fakeSequence()->push([]);

        expect(($this->failureOf)(fn () => ($this->publish)()))->toBeInstanceOf(GoogleApiFailure::class);
    });
});

describe('an appointment with no known event', function () {
    it('looks the event up by its appointment tag, deleted events included', function () {
        Http::fakeSequence()->push(['items' => []])->push(['id' => 'evt-fresh']);

        ($this->publish)(null);

        $lookup = Http::recorded()->first()[0];

        expect($lookup->method())->toBe('GET')
            ->and(strtok($lookup->url(), '?'))->toBe($this->eventsUrl)
            ->and(($this->queryOf)($lookup))->toBe([
                'privateExtendedProperty' => 'mizita_appointment_id='.IntegrationsFixtures::APPOINTMENT_ID,
                'showDeleted' => 'true',
                'maxResults' => '1',
            ]);
    });

    it('patches the tagged event it found instead of inserting a duplicate', function () {
        Http::fakeSequence()->push(['items' => [['id' => 'evt-tagged']]])->push(['id' => 'evt-tagged']);

        expect(($this->publish)(null))->toBe('evt-tagged')
            ->and(($this->sent)())->toBe([
                ['method' => 'GET', 'url' => $this->eventsUrl],
                ['method' => 'PATCH', 'url' => $this->eventsUrl.'/evt-tagged'],
            ]);
    });

    it('restores an event deleted in Google by patching it back as confirmed', function () {
        Http::fakeSequence()
            ->push(['items' => [['id' => 'evt-deleted', 'status' => 'cancelled']]])
            ->push(['id' => 'evt-deleted', 'status' => 'confirmed']);

        expect(($this->publish)(null))->toBe('evt-deleted');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
            && ($request->data()['status'] ?? null) === 'confirmed');
    });

    it('inserts the event when nothing carries the tag', function () {
        Http::fakeSequence()->push(['items' => []])->push(['id' => 'evt-fresh']);

        expect(($this->publish)(null))->toBe('evt-fresh')
            ->and(($this->sent)())->toBe([
                ['method' => 'GET', 'url' => $this->eventsUrl],
                ['method' => 'POST', 'url' => $this->eventsUrl],
            ]);
    });

    it('inserts the event when the tagged one vanished between the lookup and the patch', function (int $status) {
        Http::fakeSequence()
            ->push(['items' => [['id' => 'evt-tagged']]])
            ->push([], $status)
            ->push(['id' => 'evt-fresh']);

        expect(($this->publish)(null))->toBe('evt-fresh');
    })->with([404, 410]);

    it('treats an empty event id in the lookup as no event at all', function () {
        Http::fakeSequence()->push(['items' => [['id' => '']]])->push(['id' => 'evt-fresh']);

        expect(($this->publish)(null))->toBe('evt-fresh');
    });

    it('fails with a Google failure when the lookup is refused', function () {
        Http::fakeSequence()->push(['error' => ['code' => 500]], 500);

        $failure = ($this->failureOf)(fn () => ($this->publish)(null));

        expect($failure)->toBeInstanceOf(GoogleApiFailure::class)
            ->and($failure->getMessage())->toContain('GET');

        Http::assertSentCount(1);
    });

    it('fails with a Google failure when the insert is refused', function () {
        Http::fakeSequence()->push(['items' => []])->push(['error' => ['code' => 403]], 403);

        $failure = ($this->failureOf)(fn () => ($this->publish)(null));

        expect($failure)->toBeInstanceOf(GoogleApiFailure::class)
            ->and($failure->getMessage())->toContain('POST');
    });
});

describe('withdrawing an event', function () {
    it('deletes the event from the connection calendar', function () {
        Http::fakeSequence()->pushStatus(204);

        $this->publisher->withdraw(IntegrationsFixtures::connection(), 'evt-known');

        expect(($this->sent)())->toBe([['method' => 'DELETE', 'url' => $this->eventsUrl.'/evt-known']]);
    });

    it('treats an event already gone as withdrawn', function (int $status) {
        Http::fakeSequence()->pushStatus($status);

        expect(($this->failureOf)(fn () => $this->publisher->withdraw(IntegrationsFixtures::connection(), 'evt-known')))
            ->toBeNull();
    })->with([404, 410]);

    it('fails with a Google failure for any other refusal', function () {
        Http::fakeSequence()->pushStatus(500);

        $failure = ($this->failureOf)(fn () => $this->publisher->withdraw(IntegrationsFixtures::connection(), 'evt-known'));

        expect($failure)->toBeInstanceOf(GoogleApiFailure::class)
            ->and($failure->getMessage())->toContain('DELETE')
            ->and($failure->getMessage())->toContain('HTTP 500');
    });

    it('lets a revoked authorization surface as the domain failure the contract declares', function () {
        $this->provider->shouldReceive('refreshToken')->once()
            ->andReturn(new Token(IntegrationsFixtures::REFRESHED_ACCESS_TOKEN, '', 3600, []));
        Http::fakeSequence()->pushStatus(401)->pushStatus(401);

        expect(fn () => $this->publisher->withdraw(IntegrationsFixtures::connection(), 'evt-known'))
            ->toThrow(CalendarAuthorizationRevoked::class);
    });
});
