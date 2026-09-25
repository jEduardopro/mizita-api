<?php

declare(strict_types=1);

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Integrations\Infrastructure\Eloquent\Casts\UtcInstant;
use App\Domains\Integrations\Infrastructure\Eloquent\Models\CalendarConnectionModel;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Domains\Integrations\ValueObjects\ConnectionStatus;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\IntegrationsFixtures;

uses(TestCase::class);

beforeEach(function () {
    $this->model = (new CalendarConnectionModel)->forceFill([
        'uuid' => IntegrationsFixtures::CONNECTION_ID,
        'account_email' => IntegrationsFixtures::ACCOUNT_EMAIL,
        'access_token' => IntegrationsFixtures::ACCESS_TOKEN,
        'refresh_token' => IntegrationsFixtures::REFRESH_TOKEN,
        'status' => ConnectionStatus::Connected,
    ]);
});

describe('its identity', function () {
    it('lives in the calendar connections table', function () {
        expect($this->model->getTable())->toBe('calendar_connections');
    });

    it('generates the uuid column and keeps the int primary key auto incrementing', function () {
        expect($this->model->uniqueIds())->toBe(['uuid'])
            ->and($this->model->getIncrementing())->toBeTrue()
            ->and($this->model->getKeyType())->toBe('int');
    });

    it('is routed by its uuid, never by the int key', function () {
        expect($this->model->getRouteKeyName())->toBe('uuid');
    });

    it('hides soft deleted rows from every query by default', function () {
        expect(CalendarConnectionModel::hasGlobalScope(SoftDeletingScope::class))->toBeTrue();
    });
});

describe('its tokens', function () {
    it('declares both tokens hidden', function () {
        expect($this->model->getHidden())->toBe(['access_token', 'refresh_token']);
    });

    it('never serializes either token', function () {
        expect($this->model->toArray())->not->toHaveKey('access_token')
            ->not->toHaveKey('refresh_token')
            ->and($this->model->toJson())
            ->not->toContain(IntegrationsFixtures::ACCESS_TOKEN)
            ->not->toContain(IntegrationsFixtures::REFRESH_TOKEN);
    });

    it('stores both tokens encrypted, never in clear', function () {
        $stored = $this->model->getAttributes();

        expect($stored['access_token'])->not->toBe(IntegrationsFixtures::ACCESS_TOKEN)
            ->and($stored['refresh_token'])->not->toBe(IntegrationsFixtures::REFRESH_TOKEN)
            ->and(Crypt::decryptString($stored['access_token']))->toBe(IntegrationsFixtures::ACCESS_TOKEN)
            ->and(Crypt::decryptString($stored['refresh_token']))->toBe(IntegrationsFixtures::REFRESH_TOKEN);
    });

    it('reads both tokens back in clear for the adapter that needs them', function () {
        expect($this->model->access_token)->toBe(IntegrationsFixtures::ACCESS_TOKEN)
            ->and($this->model->refresh_token)->toBe(IntegrationsFixtures::REFRESH_TOKEN);
    });
});

describe('its casts', function () {
    it('reads the provider and the status as enums', function () {
        $model = (new CalendarConnectionModel)->setRawAttributes(['provider' => 'google', 'status' => 'needs_reconnect']);

        expect($model->provider)->toBe(CalendarProvider::Google)
            ->and($model->status)->toBe(ConnectionStatus::NeedsReconnect);
    });

    it('keeps both instants as UTC instants', function () {
        expect($this->model->getCasts())
            ->toHaveKey('access_token_expires_at', UtcInstant::class)
            ->toHaveKey('connected_at', UtcInstant::class);
    });
});

describe('its neighbours', function () {
    it('belongs to a business through business_id', function () {
        $business = $this->model->business();

        expect($business->getRelated())->toBeInstanceOf(BusinessModel::class)
            ->and($business->getForeignKeyName())->toBe('business_id');
    });

    it('still reads a staff member that was soft deleted', function () {
        $staffMember = $this->model->staffMember();

        expect($staffMember->getRelated())->toBeInstanceOf(StaffMemberModel::class)
            ->and($staffMember->getForeignKeyName())->toBe('staff_member_id')
            ->and($staffMember->getQuery()->removedScopes())->toContain(SoftDeletingScope::class);
    });
});
