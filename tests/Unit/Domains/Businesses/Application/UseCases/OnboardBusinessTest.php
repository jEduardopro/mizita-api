<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\BusinessData;
use App\Domains\Businesses\Application\UseCases\OnboardBusiness;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Contracts\IndustryCatalog;
use App\Domains\Businesses\Contracts\OwnerRegistrar;
use App\Domains\Businesses\Contracts\PhoneBook;
use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Events\BusinessCreated;
use App\Domains\Businesses\Exceptions\BusinessNameAlreadyTaken;
use App\Domains\Businesses\Exceptions\BusinessNameNotSluggable;
use App\Domains\Businesses\Exceptions\BusinessSlugAlreadyTaken;
use App\Domains\Businesses\Exceptions\InvalidBusinessTimezone;
use App\Domains\Businesses\Exceptions\OwnerAlreadyHasBusiness;
use App\Domains\Businesses\Exceptions\UnknownIndustry;
use App\Domains\Businesses\Services\SlugAllocator;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\TransactionManager;
use App\Shared\ValueObjects\CountryCode;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;

/*
| Built from mocks alone: no container, no migrations, no database. That is the
| bar this architecture exists to protect, and this is the use case that proves
| it - it writes three tables through three ports and still never touches one.
|
| SlugAllocator is instantiated for real rather than doubled: it is a pure
| domain service, so a double would assert only that the test can return a slug.
*/

beforeEach(function () {
    $this->businesses = Mockery::mock(BusinessRepository::class);
    $this->industries = Mockery::mock(IndustryCatalog::class);
    $this->owners = Mockery::mock(OwnerRegistrar::class);
    $this->phones = Mockery::mock(PhoneBook::class);
    $this->events = Mockery::mock(Dispatcher::class);
    $this->transactions = new FakeTransactionManager;

    $this->useCase = new OnboardBusiness(
        $this->businesses,
        $this->industries,
        $this->owners,
        $this->phones,
        new SlugAllocator,
        new FixedIdGenerator(OnboardingFixtures::GENERATED_BUSINESS_ID),
        new FakeClock(OnboardingFixtures::now()),
        $this->transactions,
        $this->events,
    );

    // The neighbour's events reach this domain as opaque payloads on their way
    // to the dispatcher. Anonymous objects, deliberately: a test that named
    // StaffMemberRegistered here would import Staff into a Businesses test, the
    // very coupling the port exists to prevent.
    $this->ownerEvents = [new stdClass, new stdClass];

    // Records whether a call happened inside the transaction, for the tests
    // that care; harmless for the ones that do not.
    $this->insideTransaction = [];
    $this->recordTransactionState = function (): bool {
        $this->insideTransaction[] = $this->transactions->isRunning();

        return true;
    };

    // The reads every path makes before the work begins. Each test overrides
    // only the answer it is about.
    $this->arrangeReads = function (array $slugsTaken = []) {
        $this->industries->shouldReceive('exists')
            ->with(OnboardingFixtures::INDUSTRY_ID)->andReturn(true);
        $this->businesses->shouldReceive('existsByName')
            ->with(OnboardingFixtures::NAME)->andReturn(false);
        $this->businesses->shouldReceive('slugsMatching')
            ->with(OnboardingFixtures::SLUG)->andReturn($slugsTaken);
    };
});

describe('onboarding a business', function () {
    it('creates the business, registers its owner and answers with the business data', function () {
        ($this->arrangeReads)();

        $saved = null;
        $this->businesses->shouldReceive('save')->once()->with(Mockery::capture($saved));
        $this->owners->shouldReceive('registerOwner')->once()
            ->with(OnboardingFixtures::GENERATED_BUSINESS_ID, OnboardingFixtures::OWNER_ACCOUNT_ID)
            ->andReturn($this->ownerEvents);
        $this->phones->shouldNotReceive('attachToBusiness');
        $this->events->shouldReceive('dispatch')->times(3);

        $data = $this->useCase->handle(OnboardingFixtures::input());

        expect($data)->toBeInstanceOf(BusinessData::class)
            ->and($data->id)->toBe(OnboardingFixtures::GENERATED_BUSINESS_ID)
            ->and($data->name)->toBe('Barbería Ñandú')
            ->and($data->slug)->toBe('barberia-nandu')
            ->and($data->timezone)->toBe('Europe/Madrid')
            ->and($data->industryId)->toBe(OnboardingFixtures::INDUSTRY_ID)
            ->and($data->createdAt)->toEqual(OnboardingFixtures::now());

        expect($saved)->toBeInstanceOf(Business::class)
            ->and($saved->id)->toBe(OnboardingFixtures::GENERATED_BUSINESS_ID)
            ->and($saved->name())->toBe('Barbería Ñandú')
            ->and($saved->slug())->toBe('barberia-nandu')
            ->and($saved->timezone())->toBe('Europe/Madrid')
            ->and($saved->industryId)->toBe(OnboardingFixtures::INDUSTRY_ID)
            ->and($saved->createdAt)->toEqual(OnboardingFixtures::now());
    });

    it('writes the address the allocator handed it, not the base one', function () {
        // Two businesses of the same name is the ordinary case, and the slug
        // that reaches the row has to be the free one - writing the base here
        // would be a unique violation on every second signup.
        ($this->arrangeReads)([OnboardingFixtures::SLUG, 'barberia-nandu-2']);

        $saved = null;
        $this->businesses->shouldReceive('save')->once()->with(Mockery::capture($saved));
        $this->owners->shouldReceive('registerOwner')->andReturn([]);
        $this->events->shouldReceive('dispatch')->once();

        $data = $this->useCase->handle(OnboardingFixtures::input());

        expect($saved->slug())->toBe('barberia-nandu-3')
            ->and($data->slug)->toBe('barberia-nandu-3');
    });

    it('trims the name before it looks it up and before it writes it', function () {
        $this->industries->shouldReceive('exists')->andReturn(true);
        // The lookup is what the trimming is for: a name padded by the browser
        // must collide with the same name typed cleanly.
        $this->businesses->shouldReceive('existsByName')->once()
            ->with(OnboardingFixtures::NAME)->andReturn(false);
        $this->businesses->shouldReceive('slugsMatching')->once()
            ->with(OnboardingFixtures::SLUG)->andReturn([]);

        $saved = null;
        $this->businesses->shouldReceive('save')->once()->with(Mockery::capture($saved));
        $this->owners->shouldReceive('registerOwner')->andReturn([]);
        $this->events->shouldReceive('dispatch')->once();

        $data = $this->useCase->handle(OnboardingFixtures::input(name: '   Barbería Ñandú   '));

        expect($saved->name())->toBe('Barbería Ñandú')
            ->and($data->name)->toBe('Barbería Ñandú');
    });

    it('stamps the business with the injected clock, never with real time', function () {
        ($this->arrangeReads)();

        $clock = new FakeClock(new DateTimeImmutable('2026-03-29T01:30:00+00:00'));
        $useCase = new OnboardBusiness(
            $this->businesses,
            $this->industries,
            $this->owners,
            $this->phones,
            new SlugAllocator,
            new FixedIdGenerator(OnboardingFixtures::GENERATED_BUSINESS_ID),
            $clock,
            $this->transactions,
            $this->events,
        );

        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->andReturn([]);
        $this->events->shouldReceive('dispatch')->once();

        expect($useCase->handle(OnboardingFixtures::input())->createdAt)
            ->toEqual(new DateTimeImmutable('2026-03-29T01:30:00+00:00'));
    });

    it('takes the owner from the input rather than from anything the client sent', function () {
        ($this->arrangeReads)();

        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->once()
            ->with(OnboardingFixtures::GENERATED_BUSINESS_ID, 'another-account-uuid')
            ->andReturn([]);
        $this->events->shouldReceive('dispatch')->once();

        $this->useCase->handle(OnboardingFixtures::input(ownerAccountId: 'another-account-uuid'));
    });
});

describe('the unit of work', function () {
    it('reads and computes before it opens a transaction', function () {
        // Holding a transaction open across work that writes nothing buys no
        // atomicity and costs a connection.
        $this->industries->shouldReceive('exists')->once()
            ->with(Mockery::on($this->recordTransactionState))->andReturn(true);
        $this->businesses->shouldReceive('existsByName')->once()
            ->with(Mockery::on($this->recordTransactionState))->andReturn(false);
        $this->businesses->shouldReceive('slugsMatching')->once()
            ->with(Mockery::on($this->recordTransactionState))->andReturn([]);
        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->andReturn([]);
        $this->events->shouldReceive('dispatch')->once();

        $this->useCase->handle(OnboardingFixtures::input());

        expect($this->insideTransaction)->toBe([false, false, false])
            ->and($this->transactions->runs())->toBe(1);
    });

    it('writes the business, the membership and the phone inside one transaction', function () {
        // A business nobody can operate is an orphan, not a half-finished
        // signup, so the three writes commit together or not at all.
        ($this->arrangeReads)();

        $this->businesses->shouldReceive('save')->once()->with(Mockery::on($this->recordTransactionState));
        $this->owners->shouldReceive('registerOwner')->once()
            ->with(Mockery::on($this->recordTransactionState), Mockery::any())
            ->andReturn([]);
        $this->phones->shouldReceive('attachToBusiness')->once()
            ->with(Mockery::on($this->recordTransactionState), Mockery::any());
        $this->events->shouldReceive('dispatch')->once();

        $this->useCase->handle(OnboardingFixtures::input(phone: OnboardingFixtures::phone()));

        expect($this->insideTransaction)->toBe([true, true, true])
            ->and($this->transactions->runs())->toBe(1);
    });
});

describe('the contact number', function () {
    beforeEach(function () {
        ($this->arrangeReads)();
        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->andReturn([]);
        $this->events->shouldReceive('dispatch')->once();
    });

    it('files the number against the business it has just created, exactly once', function () {
        $phone = OnboardingFixtures::phone();

        $this->phones->shouldReceive('attachToBusiness')->once()
            ->with(OnboardingFixtures::GENERATED_BUSINESS_ID, $phone);

        $this->useCase->handle(OnboardingFixtures::input(phone: $phone));
    });

    it('files the number it was handed, whatever country it belongs to', function (CountryCode $country, string $national) {
        $phone = OnboardingFixtures::phone($country, $national);

        $filed = null;
        $this->phones->shouldReceive('attachToBusiness')->once()
            ->with(OnboardingFixtures::GENERATED_BUSINESS_ID, Mockery::capture($filed));

        $this->useCase->handle(OnboardingFixtures::input(phone: $phone));

        expect($filed->equals($phone))->toBeTrue();
    })->with([
        'mexico' => [CountryCode::Mx, '5512345678'],
        'the united states' => [CountryCode::Us, '2125550147'],
    ]);

    it('never touches the phone book when no number was given', function () {
        // Optional at signup: an owner can add one later, and an absent number
        // must not become a blank row.
        $this->phones->shouldNotReceive('attachToBusiness');

        $this->useCase->handle(OnboardingFixtures::input());
    });
});

describe('announcing what happened', function () {
    it('announces nothing until the transaction has closed', function () {
        // Dispatching inside the closure would deliver BusinessCreated for a row
        // a later rollback takes away, and a listener cannot un-send a welcome
        // email. This is the assertion that protects the shape of the code.
        ($this->arrangeReads)();
        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->andReturn($this->ownerEvents);

        $this->events->shouldReceive('dispatch')->times(3)
            ->with(Mockery::on($this->recordTransactionState));

        $this->useCase->handle(OnboardingFixtures::input());

        expect($this->insideTransaction)->toBe([false, false, false])
            ->and($this->transactions->runs())->toBe(1);
    });

    it('announces the business before anything that refers to it', function () {
        // Order is the contract: a listener on the owner's registration may
        // reasonably expect the business to have been announced already.
        ($this->arrangeReads)();
        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->andReturn($this->ownerEvents);

        $announced = [];
        $this->events->shouldReceive('dispatch')->times(3)
            ->with(Mockery::on(function (object $event) use (&$announced): bool {
                $announced[] = $event;

                return true;
            }));

        $this->useCase->handle(OnboardingFixtures::input());

        expect($announced[0])->toBeInstanceOf(BusinessCreated::class)
            ->and($announced[0]->id)->toBe(OnboardingFixtures::GENERATED_BUSINESS_ID)
            // Passed through untouched, in the order the port returned them.
            ->and($announced[1])->toBe($this->ownerEvents[0])
            ->and($announced[2])->toBe($this->ownerEvents[1]);
    });

    it('announces only the business when the owner registration earned no events', function () {
        ($this->arrangeReads)();
        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->andReturn([]);

        $this->events->shouldReceive('dispatch')->once()->with(Mockery::type(BusinessCreated::class));

        $this->useCase->handle(OnboardingFixtures::input());
    });

    it('announces nothing when the commit itself fails', function () {
        // Every write in the closure succeeded and the outcome came back - and
        // then none of it survived. The events were still waiting, which is the
        // whole reason they are carried out of the transaction rather than
        // dispatched inside it.
        ($this->arrangeReads)();
        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->andReturn($this->ownerEvents);
        $this->phones->shouldReceive('attachToBusiness')->once();

        $this->events->shouldNotReceive('dispatch');

        $commitFailure = new RuntimeException('the connection went away before COMMIT');
        $this->transactions->failAtCommit($commitFailure);

        try {
            $this->useCase->handle(OnboardingFixtures::input(phone: OnboardingFixtures::phone()));
            $thrown = null;
        } catch (Throwable $failure) {
            $thrown = $failure;
        }

        expect($thrown)->toBe($commitFailure);
    });
});

describe('refusing to onboard', function () {
    it('refuses an industry the catalog does not know, before anything else happens', function () {
        $this->industries->shouldReceive('exists')->once()
            ->with('not-a-real-industry')->andReturn(false);

        $this->businesses->shouldNotReceive('existsByName');
        $this->businesses->shouldNotReceive('slugsMatching');
        $this->businesses->shouldNotReceive('save');
        $this->owners->shouldNotReceive('registerOwner');
        $this->phones->shouldNotReceive('attachToBusiness');
        $this->events->shouldNotReceive('dispatch');

        expect(fn () => $this->useCase->handle(OnboardingFixtures::input(industryId: 'not-a-real-industry')))
            ->toThrow(UnknownIndustry::class, 'Industry [not-a-real-industry] is not in the catalog.');

        expect($this->transactions->runs())->toBe(0);
    });

    it('refuses a name another business already trades under', function () {
        $this->industries->shouldReceive('exists')->andReturn(true);
        $this->businesses->shouldReceive('existsByName')->once()
            ->with(OnboardingFixtures::NAME)->andReturn(true);

        $this->businesses->shouldNotReceive('slugsMatching');
        $this->businesses->shouldNotReceive('save');
        $this->owners->shouldNotReceive('registerOwner');
        $this->phones->shouldNotReceive('attachToBusiness');
        $this->events->shouldNotReceive('dispatch');

        expect(fn () => $this->useCase->handle(OnboardingFixtures::input()))
            ->toThrow(BusinessNameAlreadyTaken::class, 'A business named [Barbería Ñandú] already exists.');

        expect($this->transactions->runs())->toBe(0);
    });

    it('refuses a name that produces no usable address', function (string $name) {
        $this->industries->shouldReceive('exists')->andReturn(true);
        $this->businesses->shouldReceive('existsByName')->andReturn(false);

        $this->businesses->shouldNotReceive('slugsMatching');
        $this->businesses->shouldNotReceive('save');
        $this->owners->shouldNotReceive('registerOwner');
        $this->phones->shouldNotReceive('attachToBusiness');
        $this->events->shouldNotReceive('dispatch');

        expect(fn () => $this->useCase->handle(OnboardingFixtures::input(name: $name)))
            ->toThrow(BusinessNameNotSluggable::class);

        expect($this->transactions->runs())->toBe(0);
    })->with([
        'punctuation only' => '...',
        'a script the alphabet does not cover' => '北京 沙龙',
        'a reserved word' => 'Admin',
    ]);

    it('refuses a time zone PHP cannot resolve', function () {
        // Every hour this business ever publishes is computed from the zone, so
        // an unresolvable one is not cosmetic.
        ($this->arrangeReads)();

        $this->businesses->shouldNotReceive('save');
        $this->owners->shouldNotReceive('registerOwner');
        $this->phones->shouldNotReceive('attachToBusiness');
        $this->events->shouldNotReceive('dispatch');

        expect(fn () => $this->useCase->handle(OnboardingFixtures::input(timezone: 'europe/madrid')))
            ->toThrow(InvalidBusinessTimezone::class, '[europe/madrid] is not a valid IANA time zone identifier.');

        expect($this->transactions->runs())->toBe(0);
    });

    it('lets the name conflict a concurrent signup won out untouched', function () {
        // No recovery by design: onboarding is a one-shot action by somebody
        // who typed the name they wanted, and adopting the winner's business
        // would hand them another person's company.
        ($this->arrangeReads)();

        $conflict = BusinessNameAlreadyTaken::for(OnboardingFixtures::NAME);
        $this->businesses->shouldReceive('save')->once()->andThrow($conflict);

        $this->owners->shouldNotReceive('registerOwner');
        $this->phones->shouldNotReceive('attachToBusiness');
        $this->events->shouldNotReceive('dispatch');

        try {
            $this->useCase->handle(OnboardingFixtures::input(phone: OnboardingFixtures::phone()));
            $thrown = null;
        } catch (Throwable $failure) {
            $thrown = $failure;
        }

        expect($thrown)->toBe($conflict)
            ->and($this->transactions->runs())->toBe(1);
    });

    it('lets the address conflict a concurrent signup won out untouched', function () {
        ($this->arrangeReads)();

        $conflict = BusinessSlugAlreadyTaken::for(OnboardingFixtures::SLUG);
        $this->businesses->shouldReceive('save')->once()->andThrow($conflict);

        $this->owners->shouldNotReceive('registerOwner');
        $this->phones->shouldNotReceive('attachToBusiness');
        $this->events->shouldNotReceive('dispatch');

        try {
            $this->useCase->handle(OnboardingFixtures::input());
            $thrown = null;
        } catch (Throwable $failure) {
            $thrown = $failure;
        }

        expect($thrown)->toBe($conflict);
    });

    it('announces nothing when the caller already owns a business', function () {
        // The business row was written and then unwritten with the rollback, so
        // BusinessCreated must not go out for it.
        ($this->arrangeReads)();

        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->once()
            ->andThrow(OwnerAlreadyHasBusiness::forAccount(OnboardingFixtures::OWNER_ACCOUNT_ID));

        $this->phones->shouldNotReceive('attachToBusiness');
        $this->events->shouldNotReceive('dispatch');

        expect(fn () => $this->useCase->handle(OnboardingFixtures::input(phone: OnboardingFixtures::phone())))
            ->toThrow(OwnerAlreadyHasBusiness::class);
    });

    it('announces nothing when filing the phone number fails', function () {
        ($this->arrangeReads)();

        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->once()->andReturn($this->ownerEvents);
        $this->phones->shouldReceive('attachToBusiness')->once()
            ->andThrow(new RuntimeException('the phone book rejected the row'));

        $this->events->shouldNotReceive('dispatch');

        expect(fn () => $this->useCase->handle(OnboardingFixtures::input(phone: OnboardingFixtures::phone())))
            ->toThrow(RuntimeException::class, 'the phone book rejected the row');
    });
});

describe('what it deliberately does not depend on', function () {
    it('takes no business context, because it is what brings a tenant into existence', function () {
        // The single named exception to the tenant rule, held in place by a test
        // rather than by a comment: there is no current business to read here -
        // the one it creates is the answer, not the input.
        $ports = array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            (new ReflectionClass(OnboardBusiness::class))->getConstructor()->getParameters(),
        );

        expect($ports)->toBe([
            BusinessRepository::class,
            IndustryCatalog::class,
            OwnerRegistrar::class,
            PhoneBook::class,
            SlugAllocator::class,
            IdGenerator::class,
            Clock::class,
            TransactionManager::class,
            Dispatcher::class,
        ])->and($ports)->not->toContain(BusinessContext::class);
    });
});
