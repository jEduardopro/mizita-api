<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\RegisterBusinessOwnerInput;
use App\Domains\Staff\Application\Dtos\StaffMemberData;
use App\Domains\Staff\Application\Dtos\StaffMemberRegistration;
use App\Domains\Staff\Application\UseCases\RegisterBusinessOwner;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\StaffProfileRepository;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Events\StaffMemberRegistered;
use App\Domains\Staff\Exceptions\AccountAlreadyOwnsBusiness;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\Application\UseCaseError;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\TransactionManager;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;
use Tests\Support\Staff\FakeStaffProfileRepository;
use Tests\Support\Staff\StaffJournal;

const REGISTERED_MEMBER_ID = '01930000-0000-7000-8000-0000000000c1';
const REGISTERED_PROFILE_ID = '01930000-0000-7000-8000-0000000000e1';
const OWNED_BUSINESS_ID = '01930000-0000-7000-8000-0000000000b1';
const OWNER_ACCOUNT = '01930000-0000-7000-8000-0000000000a1';

beforeEach(function () {
    $this->staffMembers = Mockery::mock(StaffMemberRepository::class);
    $this->transactions = new FakeTransactionManager;
    $this->journal = new StaffJournal($this->transactions);
    $this->profiles = new FakeStaffProfileRepository($this->journal);
    $this->now = new DateTimeImmutable('2026-01-01T12:00:00+00:00');

    $this->build = fn (
        ?IdGenerator $ids = null,
        ?Clock $clock = null,
    ): RegisterBusinessOwner => new RegisterBusinessOwner(
        $this->staffMembers,
        $this->profiles,
        $ids ?? new FixedIdGenerator(REGISTERED_MEMBER_ID, REGISTERED_PROFILE_ID),
        $clock ?? new FakeClock($this->now),
        $this->transactions,
    );

    $this->useCase = ($this->build)();

    $this->input = new RegisterBusinessOwnerInput(OWNED_BUSINESS_ID, OWNER_ACCOUNT);

    $this->memberIsSaved = function (?StaffMember &$saved = null): void {
        $this->staffMembers->shouldReceive('save')->once()->andReturnUsing(
            function (StaffMember $member) use (&$saved): void {
                $saved = $member;
                $this->journal->record('members.save');
            },
        );
    };

    $this->answer = fn (
        RegisterBusinessOwnerInput $input,
        ?RegisterBusinessOwner $useCase = null,
    ): UseCaseResponse => ($useCase ?? $this->useCase)->handle($input);

    $this->register = fn (
        RegisterBusinessOwnerInput $input,
        ?RegisterBusinessOwner $useCase = null,
    ): StaffMemberRegistration => ($this->answer)($input, $useCase)->value();

    $this->refuse = function (
        RegisterBusinessOwnerInput $input,
        ?RegisterBusinessOwner $useCase = null,
    ): UseCaseError {
        $response = ($this->answer)($input, $useCase);

        expect($response->failed())->toBeTrue();

        return $response->error();
    };
});

describe('registering the owner', function () {
    it('writes the membership and answers with it', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->once()
            ->with(OWNER_ACCOUNT)->andReturn(false);

        $saved = null;
        ($this->memberIsSaved)($saved);

        $registration = ($this->register)($this->input);

        expect($registration)->toBeInstanceOf(StaffMemberRegistration::class)
            ->and($registration->member)->toBeInstanceOf(StaffMemberData::class)
            ->and($registration->member->id)->toBe(REGISTERED_MEMBER_ID)
            ->and($registration->member->businessId)->toBe(OWNED_BUSINESS_ID)
            ->and($registration->member->accountId)->toBe(OWNER_ACCOUNT)
            ->and($registration->member->role)->toBe(StaffRole::Owner)
            ->and($registration->member->createdAt)->toEqual($this->now);

        expect($saved)->toBeInstanceOf(StaffMember::class)
            ->and($saved->id)->toBe(REGISTERED_MEMBER_ID)
            ->and($saved->businessId)->toBe(OWNED_BUSINESS_ID)
            ->and($saved->accountId)->toBe(OWNER_ACCOUNT)
            ->and($saved->role())->toBe(StaffRole::Owner)
            ->and($saved->createdAt)->toEqual($this->now);
    });

    it('registers at the business the input names', function (string $businessId) {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->andReturn(false);

        $saved = null;
        ($this->memberIsSaved)($saved);

        $registration = ($this->register)(new RegisterBusinessOwnerInput($businessId, OWNER_ACCOUNT));

        expect($saved->businessId)->toBe($businessId)
            ->and($registration->member->businessId)->toBe($businessId)
            ->and($this->profiles->saved[0]->businessId)->toBe($businessId);
    })->with([
        'the business just created' => OWNED_BUSINESS_ID,
        'any other business' => '01930000-0000-7000-8000-0000000000b2',
    ]);

    it('stamps the membership with the injected clock', function () {
        $useCase = ($this->build)(clock: new FakeClock(new DateTimeImmutable('2026-10-25T02:30:00+02:00')));

        $this->staffMembers->shouldReceive('ownsAnyBusiness')->andReturn(false);
        ($this->memberIsSaved)();

        expect(($this->register)($this->input, $useCase)->member->createdAt)
            ->toEqual(new DateTimeImmutable('2026-10-25T02:30:00+02:00'));
    });
});

describe('the profile that comes with the membership', function () {
    beforeEach(function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->andReturn(false);
    });

    it('creates a blank profile for the member it just registered, at the same business', function () {
        ($this->memberIsSaved)();

        ($this->register)($this->input);

        $profile = $this->profiles->saved[0];

        expect($this->profiles->saved)->toHaveCount(1)
            ->and($profile->id)->toBe(REGISTERED_PROFILE_ID)
            ->and($profile->staffMemberId)->toBe(REGISTERED_MEMBER_ID)
            ->and($profile->businessId)->toBe(OWNED_BUSINESS_ID)
            ->and($profile->jobTitle())->toBeNull()
            ->and($profile->about())->toBeNull()
            ->and($profile->createdAt)->toEqual($this->now);
    });

    it('hands the first id to the membership and the second to the profile', function () {
        $saved = null;
        ($this->memberIsSaved)($saved);

        ($this->register)($this->input);

        expect($saved->id)->toBe(REGISTERED_MEMBER_ID)
            ->and($this->profiles->saved[0]->id)->toBe(REGISTERED_PROFILE_ID)
            ->and($this->profiles->saved[0]->id)->not->toBe($saved->id);
    });

    it('stamps the membership and the profile with one reading of the clock', function () {
        $saved = null;
        ($this->memberIsSaved)($saved);

        ($this->register)($this->input);

        expect($this->profiles->saved[0]->createdAt)->toEqual($saved->createdAt);
    });

    it('writes the membership before the profile that points at it, both in one transaction', function () {
        ($this->memberIsSaved)();

        ($this->register)($this->input);

        expect($this->journal->entries)->toBe(['members.save', 'profiles.save'])
            ->and($this->journal->outsideTransaction)->toBe([])
            ->and($this->transactions->runs())->toBe(1);
    });

    it('fails the whole registration when the profile cannot be written, from inside the transaction', function () {
        ($this->memberIsSaved)();
        $missing = StaffMemberNotFound::withId(REGISTERED_MEMBER_ID);
        $this->profiles->refuseSaveWith($missing);

        $error = ($this->refuse)($this->input);

        expect($error->code)->toBe('staff_member_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound)
            ->and($error->cause())->toBe($missing)
            ->and($this->journal->entries)->toBe(['members.save', 'profiles.save'])
            ->and($this->journal->outsideTransaction)->toBe([])
            ->and($this->profiles->saved)->toBe([]);
    });
});

describe('the event it earned', function () {
    it('hands the event back instead of dispatching one', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->andReturn(false);
        ($this->memberIsSaved)();

        $registration = ($this->register)($this->input);

        expect($registration->events)->toHaveCount(1)
            ->and($registration->events[0])->toBeInstanceOf(StaffMemberRegistered::class)
            ->and($registration->events[0]->id)->toBe(REGISTERED_MEMBER_ID)
            ->and($registration->events[0]->businessId)->toBe(OWNED_BUSINESS_ID)
            ->and($registration->events[0]->role)->toBe(StaffRole::Owner);
    });

    it('keeps no state between calls', function () {
        $useCase = ($this->build)(new FixedIdGenerator(
            REGISTERED_MEMBER_ID,
            REGISTERED_PROFILE_ID,
            '01930000-0000-7000-8000-0000000000c2',
            '01930000-0000-7000-8000-0000000000e2',
        ));

        $this->staffMembers->shouldReceive('ownsAnyBusiness')->andReturn(false);
        $this->staffMembers->shouldReceive('save')->twice();

        $first = ($this->register)($this->input, $useCase);
        $second = ($this->register)(
            new RegisterBusinessOwnerInput(OWNED_BUSINESS_ID, 'another-account-uuid'),
            $useCase,
        );

        expect($first->events)->toHaveCount(1)
            ->and($second->events)->toHaveCount(1)
            ->and($second->events[0]->id)->toBe('01930000-0000-7000-8000-0000000000c2')
            ->and($this->profiles->saved[1]->staffMemberId)->toBe('01930000-0000-7000-8000-0000000000c2');
    });
});

describe('an account that already owns a business', function () {
    it('refuses, naming the account', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->once()
            ->with(OWNER_ACCOUNT)->andReturn(true);

        $this->staffMembers->shouldNotReceive('save');

        $error = ($this->refuse)($this->input);

        expect($error->code)->toBe('owner_already_has_business')
            ->and($error->cause()->getMessage())->toBe('Account ['.OWNER_ACCOUNT.'] already owns a business.');
    });

    it('opens no transaction and writes no profile', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->once()->andReturn(true);
        $this->staffMembers->shouldNotReceive('save');

        ($this->refuse)($this->input);

        expect($this->transactions->runs())->toBe(0)
            ->and($this->journal->entries)->toBe([])
            ->and($this->profiles->saved)->toBe([]);
    });

    it('asks the question platform-wide, not at one business', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->once()
            ->with(OWNER_ACCOUNT)->andReturn(true);

        expect(($this->refuse)($this->input)->code)->toBe('owner_already_has_business');
    });

    it('lets the racing conflict out untouched when the index is what refuses, before any profile is written', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->andReturn(false);

        $conflict = AccountAlreadyOwnsBusiness::forAccount(OWNER_ACCOUNT);
        $this->staffMembers->shouldReceive('save')->once()->andThrow($conflict);

        $error = ($this->refuse)($this->input);

        expect($error->code)->toBe('owner_already_has_business')
            ->and($error->cause())->toBe($conflict)
            ->and($this->profiles->saved)->toBe([])
            ->and($this->journal->entries)->toBe([]);
    });
});

describe('the shape of the answer', function () {
    it('succeeds with the registration and no warnings when everything holds', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->andReturn(false);
        ($this->memberIsSaved)();

        $response = ($this->answer)($this->input);

        expect($response->succeeded())->toBeTrue()
            ->and($response->failed())->toBeFalse()
            ->and($response->warnings())->toBe([])
            ->and($response->value())->toBeInstanceOf(StaffMemberRegistration::class);
    });

    it('answers with a failure instead of throwing when a domain rule refuses', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->andReturn(true);
        $this->staffMembers->shouldNotReceive('save');

        $response = ($this->answer)($this->input);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('owner_already_has_business')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($response->error()->cause())->toBeInstanceOf(AccountAlreadyOwnsBusiness::class);
    });

    it('lets a programmer error escape rather than dressing it as a domain failure', function () {
        $bug = new RuntimeException('the staff members table is gone');
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->once()->andThrow($bug);
        $this->staffMembers->shouldNotReceive('save');

        try {
            $this->useCase->handle($this->input);
            $thrown = null;
        } catch (Throwable $failure) {
            $thrown = $failure;
        }

        expect($thrown)->toBe($bug);
    });

    it('rethrows the original exception when the caller unwraps a failure', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->andReturn(false);

        $conflict = AccountAlreadyOwnsBusiness::forAccount(OWNER_ACCOUNT);
        $this->staffMembers->shouldReceive('save')->once()->andThrow($conflict);

        $response = ($this->answer)($this->input);

        expect(fn () => $response->value())->toThrow($conflict);
    });
});

describe('what it deliberately does not depend on', function () {
    it('takes no business context and no dispatcher', function () {
        $ports = array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            (new ReflectionClass(RegisterBusinessOwner::class))->getConstructor()->getParameters(),
        );

        expect($ports)->toBe([
            StaffMemberRepository::class,
            StaffProfileRepository::class,
            IdGenerator::class,
            Clock::class,
            TransactionManager::class,
        ])
            ->and($ports)->not->toContain(BusinessContext::class)
            ->and($ports)->not->toContain(Dispatcher::class);
    });
});
