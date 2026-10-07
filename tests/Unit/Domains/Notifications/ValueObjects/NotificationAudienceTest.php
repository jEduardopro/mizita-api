<?php

declare(strict_types=1);

use App\Domains\Notifications\ValueObjects\NotificationAudience;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

it('filters by no recipient when it covers the whole team', function () {
    expect(NotificationAudience::wholeTeam()->recipientFilter())->toBeNull();
});

it('filters by the one staff member it is addressed to', function () {
    expect(NotificationAudience::addressedTo(NotificationsFixtures::MEMBER_ID)->recipientFilter())
        ->toBe(NotificationsFixtures::MEMBER_ID);
});
