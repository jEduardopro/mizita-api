<?php

declare(strict_types=1);

use App\Domains\Integrations\Infrastructure\Google\GoogleAccessTokens;
use App\Domains\Integrations\Infrastructure\Google\GoogleApiFailure;
use App\Domains\Integrations\Infrastructure\Google\GoogleCalendarApi;
use App\Domains\Integrations\Infrastructure\Google\HttpGoogleEventSource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeClock;
use Tests\TestCase;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\GoogleOAuthDoubles;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\IntegrationsFixtures;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\StoredCalendarCredentials;

uses(TestCase::class);

beforeEach(function () {
    Http::preventStrayRequests();

    $this->credentials = StoredCalendarCredentials::install()->hold();
    $this->source = new HttpGoogleEventSource(new GoogleCalendarApi(
        new GoogleAccessTokens(GoogleOAuthDoubles::clientOver(GoogleOAuthDoubles::provider()), new FakeClock(IntegrationsFixtures::now())),
        3,
    ));

    $this->from = new DateTimeImmutable('2026-03-29T00:00:00+01:00');
    $this->to = new DateTimeImmutable('2026-03-30T00:00:00+02:00');

    $this->itemsBetween = fn () => $this->source->itemsBetween(IntegrationsFixtures::connection(), $this->from, $this->to);

    $this->queries = fn (): array => Http::recorded()
        ->map(function (array $exchange): array {
            parse_str((string) parse_url($exchange[0]->url(), PHP_URL_QUERY), $query);

            return $query;
        })
        ->all();
});

afterEach(function () {
    $this->credentials->uninstall();
});

describe('the window it asks for', function () {
    beforeEach(function () {
        Http::fakeSequence()->push(['items' => []]);

        ($this->itemsBetween)();

        $this->query = ($this->queries)()[0];
    });

    it('reads the events of the connection calendar', function () {
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && strtok($request->url(), '?') === IntegrationsFixtures::CALENDAR_API.'/calendars/'.IntegrationsFixtures::ENCODED_CALENDAR_ID.'/events');
    });

    it('expands recurring events into single instances', function () {
        expect($this->query['singleEvents'])->toBe('true');
    });

    it('leaves deleted events out', function () {
        expect($this->query['showDeleted'])->toBe('false');
    });

    it('bounds the read by the window, keeping each instant with its own offset across a spring-forward day', function () {
        expect($this->query['timeMin'])->toBe('2026-03-29T00:00:00+01:00')
            ->and($this->query['timeMax'])->toBe('2026-03-30T00:00:00+02:00');
    });

    it('asks only for the fields busy times need', function () {
        expect($this->query['fields'])->toBe('items(id,status,transparency,start,end,extendedProperties/private),nextPageToken')
            ->and($this->query['maxResults'])->toBe('250');
    });

    it('sends no page token on the first page', function () {
        expect($this->query)->not->toHaveKey('pageToken');
    });
});

describe('the items it hands back', function () {
    it('hands back every item of a single page', function () {
        Http::fakeSequence()->push(['items' => [['id' => 'evt-1'], ['id' => 'evt-2']]]);

        expect(($this->itemsBetween)())->toBe([['id' => 'evt-1'], ['id' => 'evt-2']]);
    });

    it('follows the page token and concatenates the pages in order', function () {
        Http::fakeSequence()
            ->push(['items' => [['id' => 'evt-1']], 'nextPageToken' => 'page-2'])
            ->push(['items' => [['id' => 'evt-2']]]);

        expect(($this->itemsBetween)())->toBe([['id' => 'evt-1'], ['id' => 'evt-2']])
            ->and(($this->queries)()[1]['pageToken'])->toBe('page-2');
    });

    it('stops after four pages however many Google offers', function () {
        Http::fake(fn (Request $request) => Http::response([
            'items' => [['id' => 'evt-'.count(Http::recorded())]],
            'nextPageToken' => 'more',
        ]));

        expect(($this->itemsBetween)())->toHaveCount(4);

        Http::assertSentCount(4);
    });

    it('stops on an empty page token', function () {
        Http::fakeSequence()->push(['items' => [['id' => 'evt-1']], 'nextPageToken' => '']);

        ($this->itemsBetween)();

        Http::assertSentCount(1);
    });

    it('hands back nothing for a page with no items', function (array $body) {
        Http::fakeSequence()->push($body);

        expect(($this->itemsBetween)())->toBe([]);
    })->with([
        'no items key' => [[]],
        'items that are not a list' => [['items' => 'nope']],
    ]);

    it('drops items that are not objects', function () {
        Http::fakeSequence()->push(['items' => ['stray', ['id' => 'evt-1'], 42, null]]);

        expect(($this->itemsBetween)())->toBe([['id' => 'evt-1']]);
    });
});

describe('a read Google does not answer', function () {
    it('fails with a Google failure when Google refuses the read', function () {
        Http::fakeSequence()->push(['error' => ['code' => 500]], 500);

        $failure = null;

        try {
            ($this->itemsBetween)();
        } catch (GoogleApiFailure $refused) {
            $failure = $refused;
        }

        expect($failure?->getMessage())->toContain('GET')->toContain('HTTP 500');
    });

    it('fails with a Google failure when a later page is refused', function () {
        Http::fakeSequence()
            ->push(['items' => [['id' => 'evt-1']], 'nextPageToken' => 'page-2'])
            ->push(['error' => ['code' => 503]], 503);

        expect(fn () => ($this->itemsBetween)())->toThrow(GoogleApiFailure::class);
    });

    it('fails with a Google failure when the read times out', function () {
        Http::fakeSequence()->pushFailedConnection('cURL error 28: Operation timed out after 3001 milliseconds');

        expect(fn () => ($this->itemsBetween)())->toThrow(GoogleApiFailure::class);
    });
});
