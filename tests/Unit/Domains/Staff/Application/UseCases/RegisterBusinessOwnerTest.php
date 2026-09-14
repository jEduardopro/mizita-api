<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\RegisterBusinessOwnerInput;
use App\Domains\Staff\Application\Dtos\StaffMemberData;
use App\Domains\Staff\Application\Dtos\StaffMemberRegistration;
use App\Domains\Staff\Application\UseCases\RegisterBusinessOwner;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Events\StaffMemberRegistered;
use App\Domains\Staff\Exceptions\AccountAlreadyOwnsBusiness;
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
use Tests\Support\FixedIdGenerator;

const REGISTERED_MEMBER_ID = '01930000-0000-7000-8000-0000000000c1';
const OWNED_BUSINESS_ID = '01930000-0000-7000-8000-0000000000b1';
const OWNER_ACCOUNT = '01930000-0000-7000-8000-0000000000a1';

beforeEach(function () {
    $this->staffMembers = Mockery::mock(StaffMemberRepository::class);
    $this->now = new DateTimeImmutable('2026-01-01T12:00:00+00:00');

    $this->useCase = new RegisterBusinessOwner(
        $this->staffMembers,
        new FixedIdGenerator(REGISTERED_MEMBER_ID),
        new FakeClock($this->now),
    );

    $this->input = new RegisterBusinessOwnerInput(OWNED_BUSINESS_ID, OWNER_ACCOUNT);

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
        $this->staffMembers->shouldReceive('save')->once()->with(Mockery::capture($saved));

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
        $this->staffMembers->shouldReceive('save')->once()->with(Mockery::capture($saved));

        $registration = ($this->register)(new RegisterBusinessOwnerInput($businessId, OWNER_ACCOUNT));

        expect($saved->businessId)->toBe($businessId)
            ->and($registration->member->businessId)->toBe($businessId);
    })->with([
        'the business just created' => OWNED_BUSINESS_ID,
        'any other business' => '01930000-0000-7000-8000-0000000000b2',
    ]);

    it('stamps the membership with the injected clock', function () {
        $clock = new FakeClock(new DateTimeImmutable('2026-10-25T02:30:00+02:00'));
        $useCase = new RegisterBusinessOwner($this->staffMembers, new FixedIdGenerator(REGISTERED_MEMBER_ID), $clock);

        $this->staffMembers->shouldReceive('ownsAnyBusiness')->andReturn(false);
        $this->staffMembers->shouldReceive('save')->once();

        expect(($this->register)($this->input, $useCase)->member->createdAt)
            ->toEqual(new DateTimeImmutable('2026-10-25T02:30:00+02:00'));
    });
});

describe('the event it earned', function () {
    it('hands the event back instead of dispatching one', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->andReturn(false);
        $this->staffMembers->shouldReceive('save')->once();

        $registration = ($this->register)($this->input);

        expect($registration->events)->toHaveCount(1)
            ->and($registration->events[0])->toBeInstanceOf(StaffMemberRegistered::class)
            ->and($registration->events[0]->id)->toBe(REGISTERED_MEMBER_ID)
            ->and($registration->events[0]->businessId)->toBe(OWNED_BUSINESS_ID)
            ->and($registration->events[0]->role)->toBe(StaffRole::Owner);
    });

    it('keeps no state between calls', function () {
        $useCase = new RegisterBusinessOwner(
            $this->staffMembers,
            new FixedIdGenerator(REGISTERED_MEMBER_ID, '01930000-0000-7000-8000-0000000000c2'),
            new FakeClock($this->now),
        );

        $this->staffMembers->shouldReceive('ownsAnyBusiness')->andReturn(false);
        $this->staffMembers->shouldReceive('save')->twice();

        $first = ($this->register)($this->input, $useCase);
        $second = ($this->register)(
            new RegisterBusinessOwnerInput(OWNED_BUSINESS_ID, 'another-account-uuid'),
            $useCase,
        );

        expect($first->events)->toHaveCount(1)
            ->and($second->events)->toHaveCount(1)
            ->and($second->events[0]->id)->toBe('01930000-0000-7000-8000-0000000000c2');
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

    it('asks the question platform-wide, not at one business', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->once()
            ->with(OWNER_ACCOUNT)->andReturn(true);

        expect(($this->refuse)($this->input)->code)->toBe('owner_already_has_business');
    });

    it('lets the racing conflict out untouched when the index is what refuses', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->andReturn(false);

        $conflict = AccountAlreadyOwnsBusiness::forAccount(OWNER_ACCOUNT);
        $this->staffMembers->shouldReceive('save')->once()->andThrow($conflict);

        $error = ($this->refuse)($this->input);

        expect($error->code)->toBe('owner_already_has_business')
            ->and($error->cause())->toBe($conflict);
    });
});

describe('the shape of the answer', function () {
    it('succeeds with the registration and no warnings when everything holds', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->andReturn(false);
        $this->staffMembers->shouldReceive('save')->once();

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
    it('takes no business context, no transaction manager and no dispatcher', function () {
        $ports = array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            (new ReflectionClass(RegisterBusinessOwner::class))->getConstructor()->getParameters(),
        );

        expect($ports)->toBe([
            StaffMemberRepository::class,
            IdGenerator::class,
            Clock::class,
        ])
            ->and($ports)->not->toContain(BusinessContext::class)
            ->and($ports)->not->toContain(TransactionManager::class)
            ->and($ports)->not->toContain(Dispatcher::class);
    });
});
