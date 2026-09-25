<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\SendTeamInvitationInput;
use App\Domains\Staff\Events\TeamMemberInvited;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Staff\StaffFixtures;

it('takes the business, the account and the password from the event it answers', function (?string $temporaryPassword) {
    $input = SendTeamInvitationInput::fromEvent(new TeamMemberInvited(
        staffMemberId: StaffFixtures::MEMBER_ID,
        businessId: FakeBusinessContext::BUSINESS_ID,
        accountId: StaffFixtures::ACCOUNT_ID,
        temporaryPassword: $temporaryPassword,
    ));

    expect($input->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
        ->and($input->accountId)->toBe(StaffFixtures::ACCOUNT_ID)
        ->and($input->temporaryPassword)->toBe($temporaryPassword);
})->with([
    'a new account' => 'Tmp-Pa55word!',
    'an account that already existed' => null,
]);
