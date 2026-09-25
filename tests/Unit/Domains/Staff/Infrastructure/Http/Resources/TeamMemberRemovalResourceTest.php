<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\TeamMemberRemovalData;
use App\Domains\Staff\Infrastructure\Http\Resources\TeamMemberRemovalResource;
use App\Domains\Staff\ValueObjects\RemovalBlocker;
use Tests\TestCase;

uses(TestCase::class);

it('serializes whether the member is removable and the blocker by its wire value, wrapped in data', function (?RemovalBlocker $blocker, array $expected) {
    $body = TeamMemberRemovalResource::make(new TeamMemberRemovalData($blocker))
        ->response()
        ->getData(true);

    expect($body)->toBe(['data' => $expected]);
})->with([
    'nothing blocking' => [null, ['removable' => true, 'blocker' => null]],
    'the owner' => [RemovalBlocker::Owner, ['removable' => false, 'blocker' => 'owner']],
    'upcoming appointments' => [RemovalBlocker::UpcomingAppointments, ['removable' => false, 'blocker' => 'upcoming_appointments']],
]);
