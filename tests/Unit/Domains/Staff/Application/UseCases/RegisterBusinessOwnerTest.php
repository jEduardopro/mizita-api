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
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\TransactionManager;
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
});

describe('registering the owner', function () {
    it('writes the membership and answers with it', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->once()
            ->with(OWNER_ACCOUNT)->andReturn(false);

        $saved = null;
        $this->staffMembers->shouldReceive('save')->once()->with(Mockery::capture($saved));

        $registration = $this->useCase->handle($this->input);

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

        $registration = $this->useCase->handle(new RegisterBusinessOwnerInput($businessId, OWNER_ACCOUNT));

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

        expect($useCase->handle($this->input)->member->createdAt)
            ->toEqual(new DateTimeImmutable('2026-10-25T02:30:00+02:00'));
    });
});

describe('the event it earned', function () {
    it('hands the event back instead of dispatching one', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->andReturn(false);
        $this->staffMembers->shouldReceive('save')->once();

        $registration = $this->useCase->handle($this->input);

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

        $first = $useCase->handle($this->input);
        $second = $useCase->handle(new RegisterBusinessOwnerInput(OWNED_BUSINESS_ID, 'another-account-uuid'));

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

        expect(fn () => $this->useCase->handle($this->input))
            ->toThrow(AccountAlreadyOwnsBusiness::class, 'Account ['.OWNER_ACCOUNT.'] already owns a business.');
    });

    it('asks the question platform-wide, not at one business', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->once()
            ->with(OWNER_ACCOUNT)->andReturn(true);

        expect(fn () => $this->useCase->handle($this->input))->toThrow(AccountAlreadyOwnsBusiness::class);
    });

    it('lets the racing conflict out untouched when the index is what refuses', function () {
        $this->staffMembers->shouldReceive('ownsAnyBusiness')->andReturn(false);

        $conflict = AccountAlreadyOwnsBusiness::forAccount(OWNER_ACCOUNT);
        $this->staffMembers->shouldReceive('save')->once()->andThrow($conflict);

        try {
            $this->useCase->handle($this->input);
            $thrown = null;
        } catch (Throwable $failure) {
            $thrown = $failure;
        }

        expect($thrown)->toBe($conflict);
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
