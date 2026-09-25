<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Listeners\NotifyInvitedTeamMember;
use App\Domains\Staff\Application\UseCases\SendTeamInvitation;
use App\Domains\Staff\Events\TeamMemberInvited;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Staff\FakeBusinessDirectory;
use Tests\Support\Staff\FakeTeamInvitationMailer;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->businesses = new FakeBusinessDirectory([FakeBusinessContext::BUSINESS_ID => StaffFixtures::BUSINESS_NAME]);
    $this->mailer = new FakeTeamInvitationMailer;
    $this->listener = new NotifyInvitedTeamMember(new SendTeamInvitation($this->businesses, $this->mailer));
    $this->event = new TeamMemberInvited(
        staffMemberId: StaffFixtures::SECOND_MEMBER_ID,
        businessId: FakeBusinessContext::BUSINESS_ID,
        accountId: StaffFixtures::SECOND_ACCOUNT_ID,
        temporaryPassword: StaffFixtures::TEMPORARY_PASSWORD,
    );
});

it('hands the event to the use case that mails the invitation', function () {
    $this->listener->handle($this->event);

    expect($this->businesses->lookups)->toBe([FakeBusinessContext::BUSINESS_ID])
        ->and($this->mailer->sent)->toHaveCount(1)
        ->and($this->mailer->sent[0]->accountId)->toBe(StaffFixtures::SECOND_ACCOUNT_ID)
        ->and($this->mailer->sent[0]->businessName)->toBe(StaffFixtures::BUSINESS_NAME)
        ->and($this->mailer->sent[0]->temporaryPassword)->toBe(StaffFixtures::TEMPORARY_PASSWORD);
});

it('lets a failure to mail escape rather than swallowing the invitation', function () {
    $this->mailer->failWith(new RuntimeException('smtp is down'));

    expect(fn () => $this->listener->handle($this->event))->toThrow(RuntimeException::class, 'smtp is down');
});

it('depends on nothing but the use case it delegates to', function () {
    $dependencies = array_map(
        static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
        (new ReflectionMethod(NotifyInvitedTeamMember::class, '__construct'))->getParameters(),
    );

    expect($dependencies)->toBe([SendTeamInvitation::class]);
});
