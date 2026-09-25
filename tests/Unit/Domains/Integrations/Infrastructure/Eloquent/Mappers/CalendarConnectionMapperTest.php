<?php

declare(strict_types=1);

use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\Infrastructure\Eloquent\Mappers\CalendarConnectionMapper;
use App\Domains\Integrations\Infrastructure\Eloquent\Models\CalendarConnectionModel;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Domains\Integrations\ValueObjects\CalendarTokens;
use App\Domains\Integrations\ValueObjects\ConnectionStatus;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use Tests\Support\FakeBusinessContext;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\IntegrationsFixtures;

beforeEach(function () {
    $this->businessKey = 42;
    $this->staffMemberKey = 57;
    $this->mapper = new CalendarConnectionMapper;

    $this->row = function (array $overrides = []): CalendarConnectionModel {
        $staffMember = new StaffMemberModel;
        $staffMember->setRawAttributes(['id' => $this->staffMemberKey, 'uuid' => IntegrationsFixtures::STAFF_MEMBER_ID], true);

        $model = new CalendarConnectionModel;
        $model->setRawAttributes([
            'id' => 7,
            'uuid' => IntegrationsFixtures::CONNECTION_ID,
            'business_id' => $this->businessKey,
            'staff_member_id' => $this->staffMemberKey,
            'provider' => 'google',
            'account_email' => IntegrationsFixtures::ACCOUNT_EMAIL,
            'access_token' => 'not-decryptable-access-token',
            'refresh_token' => 'not-decryptable-refresh-token',
            'access_token_expires_at' => '2026-03-29 11:00:00+00',
            'external_calendar_id' => IntegrationsFixtures::CALENDAR_ID,
            'status' => 'needs_reconnect',
            'connected_at' => '2026-03-29 12:00:00+02',
            ...$overrides,
        ], true);
        $model->setRelation('staffMember', $staffMember);

        return $model;
    };
});

describe('reading a row', function () {
    it('restores every value the row carries', function () {
        $connection = $this->mapper->toEntity(($this->row)(), FakeBusinessContext::BUSINESS_ID);

        expect($connection)->toBeInstanceOf(CalendarConnection::class)
            ->and($connection->id)->toBe(IntegrationsFixtures::CONNECTION_ID)
            ->and($connection->provider)->toBe(CalendarProvider::Google)
            ->and($connection->accountEmail())->toBe(IntegrationsFixtures::ACCOUNT_EMAIL)
            ->and($connection->externalCalendarId())->toBe(IntegrationsFixtures::CALENDAR_ID)
            ->and($connection->status())->toBe(ConnectionStatus::NeedsReconnect);
    });

    it('reads the connection instant back in UTC', function () {
        $connectedAt = $this->mapper->toEntity(($this->row)(), FakeBusinessContext::BUSINESS_ID)->connectedAt();

        expect($connectedAt->format(DATE_ATOM))->toBe('2026-03-29T10:00:00+00:00');
    });

    it('takes the business as the uuid it was handed, never the key the row holds', function () {
        $connection = $this->mapper->toEntity(($this->row)(), FakeBusinessContext::BUSINESS_ID);

        expect($connection->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($connection->businessId)->not->toBe((string) $this->businessKey);
    });

    it('reads the staff member by the uuid on the relation, never by the key on the row', function () {
        $connection = $this->mapper->toEntity(($this->row)(), FakeBusinessContext::BUSINESS_ID);

        expect($connection->staffMemberId)->toBe(IntegrationsFixtures::STAFF_MEMBER_ID)
            ->and($connection->staffMemberId)->not->toBe((string) $this->staffMemberKey);
    });

    it('restores the entity without ever decrypting the tokens', function () {
        $connection = $this->mapper->toEntity(($this->row)(), FakeBusinessContext::BUSINESS_ID);

        expect($connection->status())->toBe(ConnectionStatus::NeedsReconnect);
    });

    it('restores a row without holding it to the invariants a connection enforces', function () {
        $connection = $this->mapper->toEntity(
            ($this->row)(['account_email' => '', 'external_calendar_id' => '']),
            FakeBusinessContext::BUSINESS_ID,
        );

        expect($connection->accountEmail())->toBe('')
            ->and($connection->externalCalendarId())->toBe('');
    });
});

describe('writing a row', function () {
    beforeEach(function () {
        $this->attributes = $this->mapper->toAttributes(
            IntegrationsFixtures::connection(),
            $this->businessKey,
            $this->staffMemberKey,
        );
    });

    it('writes exactly the columns the connection owns', function () {
        expect($this->attributes)->toEqual([
            'uuid' => IntegrationsFixtures::CONNECTION_ID,
            'business_id' => $this->businessKey,
            'staff_member_id' => $this->staffMemberKey,
            'provider' => CalendarProvider::Google,
            'account_email' => IntegrationsFixtures::ACCOUNT_EMAIL,
            'external_calendar_id' => IntegrationsFixtures::CALENDAR_ID,
            'status' => ConnectionStatus::Connected,
            'connected_at' => IntegrationsFixtures::now(),
        ]);
    });

    it('writes the business and the staff member as the keys it was handed, never as uuids', function () {
        expect($this->attributes['business_id'])->toBeInt()->toBe($this->businessKey)
            ->and($this->attributes['staff_member_id'])->toBeInt()->toBe($this->staffMemberKey);
    });

    it('never writes the tokens along with the connection', function () {
        expect($this->attributes)->not->toHaveKey('access_token')
            ->not->toHaveKey('refresh_token')
            ->not->toHaveKey('access_token_expires_at');
    });
});

describe('writing the tokens', function () {
    it('writes exactly the three token columns', function () {
        $expiresAt = new DateTimeImmutable('2026-03-29T11:00:00+00:00');

        expect($this->mapper->tokenAttributes(new CalendarTokens(
            accessToken: IntegrationsFixtures::ACCESS_TOKEN,
            refreshToken: IntegrationsFixtures::REFRESH_TOKEN,
            accessTokenExpiresAt: $expiresAt,
        )))->toBe([
            'access_token' => IntegrationsFixtures::ACCESS_TOKEN,
            'refresh_token' => IntegrationsFixtures::REFRESH_TOKEN,
            'access_token_expires_at' => $expiresAt,
        ]);
    });
});
