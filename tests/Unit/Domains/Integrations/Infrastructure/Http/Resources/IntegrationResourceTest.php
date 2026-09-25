<?php

declare(strict_types=1);

use App\Domains\Integrations\Application\Dtos\CalendarConnectionData;
use App\Domains\Integrations\Application\Dtos\IntegrationData;
use App\Domains\Integrations\Infrastructure\Http\Resources\IntegrationResource;
use App\Domains\Integrations\ValueObjects\ConnectionStatus;
use App\Domains\Integrations\ValueObjects\IntegrationCategory;
use App\Domains\Integrations\ValueObjects\IntegrationKey;
use Tests\TestCase;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\IntegrationsFixtures;

uses(TestCase::class);

beforeEach(function () {
    $this->connected = new IntegrationData(
        key: IntegrationKey::GoogleCalendar,
        category: IntegrationCategory::CalendarSync,
        connection: CalendarConnectionData::fromEntity(IntegrationsFixtures::connection()),
    );

    $this->available = new IntegrationData(
        key: IntegrationKey::GoogleCalendar,
        category: IntegrationCategory::CalendarSync,
        connection: null,
    );

    $this->envelopeOf = fn (IntegrationData $integration): array => (array) IntegrationResource::make($integration)
        ->response()
        ->getData(true);

    $this->serialized = fn (IntegrationData $integration): array => (array) ($this->envelopeOf)($integration)['data'];
});

describe('the client contract', function () {
    it('wraps the payload in the data envelope and nothing else', function () {
        expect(array_keys(($this->envelopeOf)($this->connected)))->toBe(['data']);
    });

    it('serializes exactly the key, the category and the connection', function () {
        expect(array_keys(($this->serialized)($this->connected)))->toBe(['key', 'category', 'connection']);
    });

    it('names the integration and its category by their wire values', function () {
        $serialized = ($this->serialized)($this->connected);

        expect($serialized['key'])->toBe('google_calendar')
            ->and($serialized['category'])->toBe('calendar_sync');
    });
});

describe('an integration nobody connected yet', function () {
    it('sends the connection as null rather than dropping the key', function () {
        $serialized = ($this->serialized)($this->available);

        expect($serialized)->toHaveKey('connection')
            ->and($serialized['connection'])->toBeNull();
    });
});

describe('a connected integration', function () {
    it('serializes the whole connection the screen reads', function () {
        expect(($this->serialized)($this->connected)['connection'])->toBe([
            'id' => IntegrationsFixtures::CONNECTION_ID,
            'status' => 'connected',
            'account_email' => IntegrationsFixtures::ACCOUNT_EMAIL,
            'connected_at' => '2026-03-29T10:00:00+00:00',
        ]);
    });

    it('identifies the connection by its uuid, never by an int key', function () {
        expect(($this->serialized)($this->connected)['connection']['id'])
            ->toBeString()
            ->toMatch('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/');
    });

    it('sends a connection awaiting a reconnect with its status', function () {
        $integration = new IntegrationData(
            key: IntegrationKey::GoogleCalendar,
            category: IntegrationCategory::CalendarSync,
            connection: CalendarConnectionData::fromEntity(IntegrationsFixtures::connection(status: ConnectionStatus::NeedsReconnect)),
        );

        expect(($this->serialized)($integration)['connection']['status'])->toBe('needs_reconnect');
    });

    it('never serializes a token, the calendar id or the staff member', function () {
        expect(json_encode(($this->serialized)($this->connected), JSON_THROW_ON_ERROR))
            ->not->toContain('token')
            ->not->toContain(IntegrationsFixtures::CALENDAR_ID)
            ->not->toContain(IntegrationsFixtures::STAFF_MEMBER_ID)
            ->not->toContain('staff_member');
    });
});
