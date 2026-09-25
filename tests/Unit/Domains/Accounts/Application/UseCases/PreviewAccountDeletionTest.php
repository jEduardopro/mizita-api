<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\AccountDeletionPreviewData;
use App\Domains\Accounts\Application\Dtos\PreviewAccountDeletionInput;
use App\Domains\Accounts\Application\UseCases\PreviewAccountDeletion;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Domains\Accounts\ValueObjects\DeletionBlocker;
use App\Domains\Accounts\ValueObjects\OwnedBusinessSnapshot;
use App\Domains\Accounts\ValueObjects\PasswordStatus;
use App\Domains\Accounts\ValueObjects\SocialProvider;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Accounts\AccountDeletionFixtures;
use Tests\Support\Accounts\FakeOwnedBusinesses;
use Tests\Support\Accounts\FakeTeamMemberships;
use Tests\Support\Accounts\FakeUpcomingBookings;
use Tests\Support\FakeClock;

const PREVIEW_OTHER_BUSINESS_ID = '01930000-0000-7000-8000-0000000000b2';

beforeEach(function () {
    $this->accounts = Mockery::mock(AccountRepository::class);
    $this->accounts->shouldNotReceive('save');
    $this->memberships = new FakeTeamMemberships;
    $this->ownedBusinesses = (new FakeOwnedBusinesses)->owning(AccountDeletionFixtures::ownedBusiness());
    $this->upcomingBookings = (new FakeUpcomingBookings)
        ->withUpcoming(AccountDeletionFixtures::BUSINESS_ID, 4)
        ->withUpcoming(PREVIEW_OTHER_BUSINESS_ID, 9);
    $this->clock = new FakeClock(AccountDeletionFixtures::now());

    $this->useCase = new PreviewAccountDeletion(
        $this->accounts,
        $this->memberships,
        $this->ownedBusinesses,
        $this->upcomingBookings,
        $this->clock,
    );

    $this->holding = function ($account): void {
        $this->accounts->shouldReceive('findById')->once()
            ->with(AccountDeletionFixtures::ACCOUNT_ID)
            ->andReturn($account);
    };

    $this->preview = fn (): AccountDeletionPreviewData => $this->useCase
        ->handle(new PreviewAccountDeletionInput(AccountDeletionFixtures::ACCOUNT_ID))
        ->value();
});

describe('an account that owns no business', function () {
    beforeEach(function () {
        ($this->holding)(AccountDeletionFixtures::activeAccount());
    });

    it('previews the deletion field by field', function () {
        $preview = ($this->preview)();

        expect($preview)->toBeInstanceOf(AccountDeletionPreviewData::class)
            ->and($preview->email)->toBe(AccountDeletionFixtures::EMAIL)
            ->and($preview->hasPassword)->toBeTrue()
            ->and($preview->ownedBusiness)->toBeNull()
            ->and($preview->upcomingAppointmentsCount)->toBe(0)
            ->and($preview->blockedBy)->toBeNull()
            ->and($preview->gracePeriodEndsAt->format(DATE_ATOM))->toBe(AccountDeletionFixtures::GRACE_PERIOD_ENDS_AT);
    });

    it('counts no appointments and describes no business', function () {
        ($this->preview)();

        expect($this->upcomingBookings->businessesCounted)->toBe([])
            ->and($this->ownedBusinesses->described)->toBe([]);
    });

    it('changes nothing', function () {
        ($this->preview)();

        expect($this->memberships->accountsThatLeft)->toBe([])
            ->and($this->ownedBusinesses->closures)->toBe([]);
    });
});

describe('the owner of a business', function () {
    beforeEach(function () {
        ($this->holding)(AccountDeletionFixtures::activeAccount());
        $this->memberships->owning(AccountDeletionFixtures::ACCOUNT_ID, AccountDeletionFixtures::BUSINESS_ID);
    });

    it('names the business that would close, by its uuid', function () {
        $preview = ($this->preview)();

        expect($preview->ownedBusiness)->toBeInstanceOf(OwnedBusinessSnapshot::class)
            ->and($preview->ownedBusiness->id)->toBe(AccountDeletionFixtures::BUSINESS_ID)
            ->and($preview->ownedBusiness->name)->toBe(AccountDeletionFixtures::BUSINESS_NAME)
            ->and($this->ownedBusinesses->described)->toBe([AccountDeletionFixtures::BUSINESS_ID]);
    });

    it('counts the upcoming appointments of the owned business only', function () {
        $preview = ($this->preview)();

        expect($preview->upcomingAppointmentsCount)->toBe(4)
            ->and($this->upcomingBookings->businessesCounted)->toBe([AccountDeletionFixtures::BUSINESS_ID]);
    });

    it('warns with the count but never blocks the owner for it', function () {
        expect(($this->preview)()->blockedBy)->toBeNull();
    });

    it('reports zero when the owned business has nothing booked', function () {
        $this->memberships->owning(AccountDeletionFixtures::ACCOUNT_ID, '01930000-0000-7000-8000-0000000000b3');
        $this->ownedBusinesses->owning(new OwnedBusinessSnapshot('01930000-0000-7000-8000-0000000000b3', 'Barbería Ñandú'));

        $preview = ($this->preview)();

        expect($preview->upcomingAppointmentsCount)->toBe(0)
            ->and($preview->ownedBusiness->name)->toBe('Barbería Ñandú');
    });

    it('is blocked as well when it has upcoming appointments in a team it does not own', function () {
        $this->memberships->busyElsewhere(AccountDeletionFixtures::ACCOUNT_ID);

        $preview = ($this->preview)();

        expect($preview->blockedBy)->toBe(DeletionBlocker::UpcomingAppointments)
            ->and($preview->upcomingAppointmentsCount)->toBe(4);
    });
});

describe('a staff member of businesses the account does not own', function () {
    it('is blocked by the upcoming appointments assigned to it', function () {
        ($this->holding)(AccountDeletionFixtures::activeAccount());
        $this->memberships->busyElsewhere(AccountDeletionFixtures::ACCOUNT_ID);

        $preview = ($this->preview)();

        expect($preview->blockedBy)->toBe(DeletionBlocker::UpcomingAppointments)
            ->and($preview->ownedBusiness)->toBeNull()
            ->and($preview->upcomingAppointmentsCount)->toBe(0)
            ->and($this->upcomingBookings->businessesCounted)->toBe([]);
    });

    it('asks about the account by its uuid', function () {
        ($this->holding)(AccountDeletionFixtures::activeAccount());

        ($this->preview)();

        expect($this->memberships->accountsAsked)->each->toBe(AccountDeletionFixtures::ACCOUNT_ID)
            ->and($this->memberships->accountsAsked)->toHaveCount(2);
    });
});

describe('how the account confirms', function () {
    it('tells the client whether a password or the email confirms the deletion', function (PasswordStatus $status, array $providers, bool $hasPassword) {
        ($this->holding)(AccountDeletionFixtures::activeAccount($status, linkedSocialProviders: $providers));

        expect(($this->preview)()->hasPassword)->toBe($hasPassword);
    })->with([
        'a chosen password' => [PasswordStatus::Chosen, [], true],
        'a temporary password' => [PasswordStatus::Temporary, [], true],
        'Google only' => [PasswordStatus::Absent, [SocialProvider::Google], false],
    ]);
});

describe('the grace period it announces', function () {
    it('ends thirty days after the clock, not after any stored instant', function () {
        ($this->holding)(AccountDeletionFixtures::activeAccount());
        $this->clock->advance('P1DT30M');

        expect(($this->preview)()->gracePeriodEndsAt->format(DATE_ATOM))->toBe('2026-02-01T12:30:00+00:00');
    });

    it('spans exactly thirty days when previewed on the night Madrid springs forward', function () {
        ($this->holding)(AccountDeletionFixtures::activeAccount());
        $clock = new FakeClock(new DateTimeImmutable('2026-03-29T01:30:00+00:00'));
        $useCase = new PreviewAccountDeletion($this->accounts, $this->memberships, $this->ownedBusinesses, $this->upcomingBookings, $clock);

        $preview = $useCase->handle(new PreviewAccountDeletionInput(AccountDeletionFixtures::ACCOUNT_ID))->value();

        expect($preview->gracePeriodEndsAt->format(DATE_ATOM))->toBe('2026-04-28T01:30:00+00:00');
    });
});

describe('an account that cannot be previewed', function () {
    it('refuses a malformed account id as not found, without looking it up', function () {
        $this->accounts->shouldNotReceive('findById');

        $response = $this->useCase->handle(new PreviewAccountDeletionInput('7'));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('account_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('answers with not found when the account does not exist', function () {
        $missing = AccountNotFound::withId(AccountDeletionFixtures::ACCOUNT_ID);
        $this->accounts->shouldReceive('findById')->once()->andThrow($missing);

        $response = $this->useCase->handle(new PreviewAccountDeletionInput(AccountDeletionFixtures::ACCOUNT_ID));

        expect($response->error()->code)->toBe('account_not_found')
            ->and($response->error()->cause())->toBe($missing)
            ->and($this->memberships->accountsAsked)->toBe([]);
    });
});
