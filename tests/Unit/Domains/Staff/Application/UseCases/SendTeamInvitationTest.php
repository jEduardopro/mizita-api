<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\SendTeamInvitationInput;
use App\Domains\Staff\Application\UseCases\SendTeamInvitation;
use App\Domains\Staff\ValueObjects\TeamInvitation;
use App\Shared\Contracts\BusinessContext;
use Tests\Support\Staff\FakeBusinessDirectory;
use Tests\Support\Staff\FakeTeamInvitationMailer;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->businesses = new FakeBusinessDirectory([StaffFixtures::OTHER_BUSINESS_ID => StaffFixtures::BUSINESS_NAME]);
    $this->mailer = new FakeTeamInvitationMailer;
    $this->useCase = new SendTeamInvitation($this->businesses, $this->mailer);
});

it('mails the account the invitation, naming the business the event came from', function (?string $temporaryPassword) {
    $response = $this->useCase->handle(new SendTeamInvitationInput(
        businessId: StaffFixtures::OTHER_BUSINESS_ID,
        accountId: StaffFixtures::SECOND_ACCOUNT_ID,
        temporaryPassword: $temporaryPassword,
    ));

    expect($response->succeeded())->toBeTrue()
        ->and($response->value())->toBeNull()
        ->and($this->businesses->lookups)->toBe([StaffFixtures::OTHER_BUSINESS_ID])
        ->and($this->mailer->sent)->toHaveCount(1)
        ->and($this->mailer->sent[0])->toBeInstanceOf(TeamInvitation::class)
        ->and($this->mailer->sent[0]->accountId)->toBe(StaffFixtures::SECOND_ACCOUNT_ID)
        ->and($this->mailer->sent[0]->businessName)->toBe(StaffFixtures::BUSINESS_NAME)
        ->and($this->mailer->sent[0]->temporaryPassword)->toBe($temporaryPassword);
})->with([
    'a new account' => StaffFixtures::TEMPORARY_PASSWORD,
    'an account that already existed' => null,
]);

it('lets a mail that could not be sent escape, so the listener fails loudly instead of losing the invitation', function () {
    $this->mailer->failWith(new RuntimeException('the account is gone'));

    expect(fn () => $this->useCase->handle(new SendTeamInvitationInput(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::SECOND_ACCOUNT_ID, null)))
        ->toThrow(RuntimeException::class, 'the account is gone');
});

it('mails nothing when the business cannot be named', function () {
    expect(fn () => $this->useCase->handle(new SendTeamInvitationInput('01930000-0000-7000-8000-0000000000b9', StaffFixtures::SECOND_ACCOUNT_ID, null)))
        ->toThrow(RuntimeException::class)
        ->and($this->mailer->sent)->toBe([]);
});

it('takes the business from its input rather than from a context a listener may not have', function () {
    $ports = array_map(
        static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
        (new ReflectionMethod(SendTeamInvitation::class, '__construct'))->getParameters(),
    );

    expect($ports)->not->toContain(BusinessContext::class);
});
