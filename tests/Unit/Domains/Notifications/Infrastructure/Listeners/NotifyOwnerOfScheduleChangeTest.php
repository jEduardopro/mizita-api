<?php

declare(strict_types=1);

use App\Domains\Availability\Events\StaffScheduleChanged;
use App\Domains\Notifications\Application\UseCases\NotifyStaffScheduleChanged;
use App\Domains\Notifications\Exceptions\NotifiedStaffMemberNotFound;
use App\Domains\Notifications\Infrastructure\Listeners\NotifyOwnerOfScheduleChange;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;
use Tests\Unit\Domains\Notifications\Application\Doubles\FakeBusinessOwners;
use Tests\Unit\Domains\Notifications\Application\Doubles\FakeStaffNotificationRepository;
use Tests\Unit\Domains\Notifications\Application\Doubles\NotificationsFixtures;

beforeEach(function () {
    $this->owners = (new FakeBusinessOwners)
        ->ownedBy(NotificationsFixtures::BUSINESS_ID, NotificationsFixtures::OWNER_MEMBER_ID);
    $this->notifications = new FakeStaffNotificationRepository;

    $this->listener = new NotifyOwnerOfScheduleChange(new NotifyStaffScheduleChanged(
        $this->owners,
        $this->notifications,
        new FixedIdGenerator(NotificationsFixtures::NEW_NOTIFICATION_ID),
        new FakeClock(NotificationsFixtures::now()),
    ));
});

it('notifies the owner of the business about the staff member whose schedule changed', function () {
    $this->listener->handle(new StaffScheduleChanged(NotificationsFixtures::BUSINESS_ID, NotificationsFixtures::MEMBER_ID));

    expect($this->owners->lookups)->toBe([NotificationsFixtures::BUSINESS_ID])
        ->and($this->notifications->addedOrRefreshed)->toHaveCount(1)
        ->and($this->notifications->addedOrRefreshed[0]->businessId)->toBe(NotificationsFixtures::BUSINESS_ID)
        ->and($this->notifications->addedOrRefreshed[0]->recipientStaffMemberId)->toBe(NotificationsFixtures::OWNER_MEMBER_ID)
        ->and($this->notifications->addedOrRefreshed[0]->subjectStaffMemberId)->toBe(NotificationsFixtures::MEMBER_ID);
});

it('rethrows a refusal so the queue retries the job', function () {
    expect(fn () => $this->listener->handle(new StaffScheduleChanged(NotificationsFixtures::BUSINESS_ID, '42')))
        ->toThrow(NotifiedStaffMemberNotFound::class);
});

it('runs only once the schedule transaction has committed', function () {
    expect($this->listener)->toBeInstanceOf(ShouldQueueAfterCommit::class);
});

it('retries three times, backing off between attempts', function () {
    expect($this->listener->tries)->toBe(3)
        ->and($this->listener->backoff())->toBe([30, 120]);
});
