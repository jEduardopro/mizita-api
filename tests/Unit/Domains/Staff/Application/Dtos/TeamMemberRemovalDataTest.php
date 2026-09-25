<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\TeamMemberRemovalData;
use App\Domains\Staff\ValueObjects\RemovalBlocker;

it('is removable only when nothing blocks the removal', function (?RemovalBlocker $blocker, bool $removable) {
    $removal = new TeamMemberRemovalData($blocker);

    expect($removal->removable())->toBe($removable)
        ->and($removal->blocker)->toBe($blocker);
})->with([
    'nothing blocking' => [null, true],
    'the owner' => [RemovalBlocker::Owner, false],
    'upcoming appointments' => [RemovalBlocker::UpcomingAppointments, false],
]);
