<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\BusinessData;
use App\Domains\Businesses\Application\Dtos\PhoneNumberInput;
use App\Domains\Businesses\Application\UseCases\OnboardBusiness;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Contracts\IndustryCatalog;
use App\Domains\Businesses\Contracts\OwnerRegistrar;
use App\Domains\Businesses\Contracts\PhoneBook;
use App\Domains\Businesses\Contracts\RoleProvisioner;
use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Events\BusinessCreated;
use App\Domains\Businesses\Exceptions\BusinessNameAlreadyTaken;
use App\Domains\Businesses\Exceptions\BusinessNameNotSluggable;
use App\Domains\Businesses\Exceptions\BusinessSlugAlreadyTaken;
use App\Domains\Businesses\Exceptions\InvalidBusinessTimezone;
use App\Domains\Businesses\Exceptions\OwnerAlreadyHasBusiness;
use App\Domains\Businesses\Exceptions\UnknownIndustry;
use App\Domains\Businesses\Exceptions\UnsupportedPhoneNumber;
use App\Domains\Businesses\Services\SlugAllocator;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\PhoneNumberParser;
use App\Shared\Contracts\TransactionManager;
use App\Shared\ValueObjects\CountryCode;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\FakeClock;
use Tests\Support\FakePhoneNumberParser;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;
use Tests\Support\PhoneNumbers;

beforeEach(function () {
    $this->businesses = Mockery::mock(BusinessRepository::class);
    $this->industries = Mockery::mock(IndustryCatalog::class);
    $this->roles = Mockery::mock(RoleProvisioner::class);
    $this->owners = Mockery::mock(OwnerRegistrar::class);
    $this->phones = Mockery::mock(PhoneBook::class);
    $this->events = Mockery::mock(Dispatcher::class);
    $this->transactions = new FakeTransactionManager;

    $this->parser = FakePhoneNumberParser::accepting(
        OnboardingFixtures::phone(),
        OnboardingFixtures::phone(CountryCode::Us, '2125550147'),
    );

    $this->build = function (?PhoneNumberParser $parser = null, ?Clock $clock = null): OnboardBusiness {
        return new OnboardBusiness(
            $this->businesses,
            $this->industries,
            $this->roles,
            $this->owners,
            $this->phones,
            new SlugAllocator,
            $parser ?? $this->parser,
            new FixedIdGenerator(OnboardingFixtures::GENERATED_BUSINESS_ID),
            $clock ?? new FakeClock(OnboardingFixtures::now()),
            $this->transactions,
            $this->events,
        );
    };

    $this->useCase = ($this->build)();

    $this->roles->shouldReceive('provisionFor')->byDefault();

    $this->ownerEvents = [new stdClass, new stdClass];

    $this->insideTransaction = [];
    $this->recordTransactionState = function (): bool {
        $this->insideTransaction[] = $this->transactions->isRunning();

        return true;
    };

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

        $useCase = ($this->build)(clock: new FakeClock(new DateTimeImmutable('2026-03-29T01:30:00+00:00')));

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

describe('the roles the business starts life with', function () {
    it('provisions them for the business it has just created, exactly once', function () {
        ($this->arrangeReads)();

        $this->roles->shouldReceive('provisionFor')->once()
            ->with(OnboardingFixtures::GENERATED_BUSINESS_ID);
        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->andReturn([]);
        $this->events->shouldReceive('dispatch')->once();

        $this->useCase->handle(OnboardingFixtures::input());
    });

    it('provisions them before the owner is registered', function () {
        ($this->arrangeReads)();

        $order = [];
        $record = function (string $step) use (&$order): bool {
            $order[] = $step;

            return true;
        };

        $this->businesses->shouldReceive('save')->once()
            ->with(Mockery::on(fn (): bool => $record('save')));
        $this->roles->shouldReceive('provisionFor')->once()
            ->with(Mockery::on(fn (): bool => $record('provision')));
        $this->owners->shouldReceive('registerOwner')->once()
            ->with(Mockery::on(fn (): bool => $record('owner')), Mockery::any())
            ->andReturn([]);
        $this->events->shouldReceive('dispatch')->once();

        $this->useCase->handle(OnboardingFixtures::input());

        expect($order)->toBe(['save', 'provision', 'owner']);
    });

    it('provisions them inside the transaction that writes the business', function () {
        ($this->arrangeReads)();

        $this->roles->shouldReceive('provisionFor')->once()
            ->with(Mockery::on($this->recordTransactionState));
        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->andReturn([]);
        $this->events->shouldReceive('dispatch')->once();

        $this->useCase->handle(OnboardingFixtures::input());

        expect($this->insideTransaction)->toBe([true]);
    });

    it('provisions nothing when the industry guard refuses the signup', function () {
        $this->roles->shouldNotReceive('provisionFor');
        $this->industries->shouldReceive('exists')->andReturn(false);
        $this->businesses->shouldNotReceive('save');
        $this->owners->shouldNotReceive('registerOwner');
        $this->events->shouldNotReceive('dispatch');

        expect(fn () => $this->useCase->handle(OnboardingFixtures::input()))
            ->toThrow(UnknownIndustry::class);
    });

    it('provisions nothing when the time zone guard refuses the signup', function () {
        ($this->arrangeReads)();

        $this->roles->shouldNotReceive('provisionFor');
        $this->businesses->shouldNotReceive('save');
        $this->owners->shouldNotReceive('registerOwner');
        $this->events->shouldNotReceive('dispatch');

        expect(fn () => $this->useCase->handle(OnboardingFixtures::input(timezone: 'europe/madrid')))
            ->toThrow(InvalidBusinessTimezone::class);
    });
});

describe('the unit of work', function () {
    it('reads and computes before it opens a transaction', function () {
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

    it('writes the business, its roles, the membership and the phone inside one transaction', function () {
        ($this->arrangeReads)();

        $this->businesses->shouldReceive('save')->once()->with(Mockery::on($this->recordTransactionState));
        $this->roles->shouldReceive('provisionFor')->once()->with(Mockery::on($this->recordTransactionState));
        $this->owners->shouldReceive('registerOwner')->once()
            ->with(Mockery::on($this->recordTransactionState), Mockery::any())
            ->andReturn([]);
        $this->phones->shouldReceive('attachToBusiness')->once()
            ->with(Mockery::on($this->recordTransactionState), Mockery::any());
        $this->events->shouldReceive('dispatch')->once();

        $this->useCase->handle(OnboardingFixtures::input(phone: OnboardingFixtures::submittedPhone()));

        expect($this->insideTransaction)->toBe([true, true, true, true])
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

    it('files the number the parser established, not the digits the caller typed', function () {
        $filed = null;
        $this->phones->shouldReceive('attachToBusiness')->once()
            ->with(OnboardingFixtures::GENERATED_BUSINESS_ID, Mockery::capture($filed));

        $this->useCase->handle(OnboardingFixtures::input(phone: OnboardingFixtures::submittedPhone()));

        expect($filed->equals(OnboardingFixtures::phone()))->toBeTrue()
            ->and($filed->e164())->toBe(PhoneNumbers::MX_E164);
    });

    it('asks the parser once, with the country declared and the string as typed', function () {
        $this->phones->shouldReceive('attachToBusiness')->once();

        $this->useCase->handle(OnboardingFixtures::input(
            phone: new PhoneNumberInput('MX', ' (55) 1234-5678 '),
        ));

        expect($this->parser->calls())->toBe([
            ['country' => CountryCode::Mx, 'nationalNumber' => ' (55) 1234-5678 '],
        ]);
    });

    it('files the number it was handed, whatever country it belongs to', function (CountryCode $country, string $national) {
        $filed = null;
        $this->phones->shouldReceive('attachToBusiness')->once()
            ->with(OnboardingFixtures::GENERATED_BUSINESS_ID, Mockery::capture($filed));

        $this->useCase->handle(OnboardingFixtures::input(
            phone: OnboardingFixtures::submittedPhone($country, $national),
        ));

        expect($filed->equals(OnboardingFixtures::phone($country, $national)))->toBeTrue();
    })->with([
        'mexico' => [CountryCode::Mx, '5512345678'],
        'the united states' => [CountryCode::Us, '2125550147'],
    ]);

    it('never touches the phone book or the parser when no number was given', function () {
        $this->phones->shouldNotReceive('attachToBusiness');

        $this->useCase->handle(OnboardingFixtures::input());

        expect($this->parser->wasConsulted())->toBeFalse();
    });
});

describe('a number the platform cannot accept', function () {
    beforeEach(function () {
        $this->industries->shouldNotReceive('exists');
        $this->businesses->shouldNotReceive('existsByName');
        $this->businesses->shouldNotReceive('slugsMatching');
        $this->businesses->shouldNotReceive('save');
        $this->roles->shouldNotReceive('provisionFor');
        $this->owners->shouldNotReceive('registerOwner');
        $this->phones->shouldNotReceive('attachToBusiness');
        $this->events->shouldNotReceive('dispatch');
    });

    it('refuses a country the platform does not operate in, without asking the parser', function (string $countryCode) {
        expect(fn () => $this->useCase->handle(
            OnboardingFixtures::input(phone: new PhoneNumberInput($countryCode, '5512345678')),
        ))->toThrow(UnsupportedPhoneNumber::class, "[{$countryCode}] is not a country this platform operates in.");

        expect($this->parser->wasConsulted())->toBeFalse()
            ->and($this->transactions->runs())->toBe(0);
    })->with([
        'a country we do not serve' => 'ES',
        'the lowercase form of one we do' => 'mx',
        'not a country at all' => 'XX',
        'blank' => '',
    ]);

    it('refuses digits that are not a number in a country it does serve', function () {
        $useCase = ($this->build)(parser: FakePhoneNumberParser::acceptingNothing());

        expect(fn () => $useCase->handle(
            OnboardingFixtures::input(phone: OnboardingFixtures::submittedPhone(CountryCode::Us, '8421133471')),
        ))->toThrow(UnsupportedPhoneNumber::class, 'The number offered is not a valid phone number in [US].');

        expect($this->transactions->runs())->toBe(0);
    });
});

describe('announcing what happened', function () {
    it('announces nothing until the transaction has closed', function () {
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
        ($this->arrangeReads)();
        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->andReturn($this->ownerEvents);
        $this->phones->shouldReceive('attachToBusiness')->once();

        $this->events->shouldNotReceive('dispatch');

        $commitFailure = new RuntimeException('the connection went away before COMMIT');
        $this->transactions->failAtCommit($commitFailure);

        try {
            $this->useCase->handle(OnboardingFixtures::input(phone: OnboardingFixtures::submittedPhone()));
            $thrown = null;
        } catch (Throwable $failure) {
            $thrown = $failure;
        }

        expect($thrown)->toBe($commitFailure);
    });
});

describe('refusing to onboard', function () {
    it('asks the catalog about a well formed industry id, and refuses the one it does not know', function () {
        $retiredIndustryId = '01930000-0000-7000-8000-0000000000f9';

        $this->industries->shouldReceive('exists')->once()
            ->with($retiredIndustryId)->andReturn(false);

        $this->businesses->shouldNotReceive('existsByName');
        $this->businesses->shouldNotReceive('slugsMatching');
        $this->businesses->shouldNotReceive('save');
        $this->owners->shouldNotReceive('registerOwner');
        $this->phones->shouldNotReceive('attachToBusiness');
        $this->events->shouldNotReceive('dispatch');

        expect(fn () => $this->useCase->handle(OnboardingFixtures::input(industryId: $retiredIndustryId)))
            ->toThrow(UnknownIndustry::class, "Industry [{$retiredIndustryId}] is not in the catalog.");

        expect($this->transactions->runs())->toBe(0);
    });

    it('refuses an industry id that is not uuid shaped without consulting the catalog', function (string $industryId) {
        $this->industries->shouldNotReceive('exists');
        $this->businesses->shouldNotReceive('existsByName');
        $this->businesses->shouldNotReceive('slugsMatching');
        $this->businesses->shouldNotReceive('save');
        $this->owners->shouldNotReceive('registerOwner');
        $this->phones->shouldNotReceive('attachToBusiness');
        $this->events->shouldNotReceive('dispatch');

        expect(fn () => $this->useCase->handle(OnboardingFixtures::input(industryId: $industryId)))
            ->toThrow(UnknownIndustry::class, "Industry [{$industryId}] is not in the catalog.");

        expect($this->transactions->runs())->toBe(0);
    })->with([
        'a word' => 'not-a-real-industry',
        'empty' => '',
        'a bare integer' => '7',
        'a uuid missing a group' => '01930000-0000-7000-000000000000',
    ]);

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
        ($this->arrangeReads)();

        $conflict = BusinessNameAlreadyTaken::for(OnboardingFixtures::NAME);
        $this->businesses->shouldReceive('save')->once()->andThrow($conflict);

        $this->roles->shouldNotReceive('provisionFor');
        $this->owners->shouldNotReceive('registerOwner');
        $this->phones->shouldNotReceive('attachToBusiness');
        $this->events->shouldNotReceive('dispatch');

        try {
            $this->useCase->handle(OnboardingFixtures::input(phone: OnboardingFixtures::submittedPhone()));
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

        $this->roles->shouldNotReceive('provisionFor');
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
        ($this->arrangeReads)();

        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->once()
            ->andThrow(OwnerAlreadyHasBusiness::forAccount(OnboardingFixtures::OWNER_ACCOUNT_ID));

        $this->phones->shouldNotReceive('attachToBusiness');
        $this->events->shouldNotReceive('dispatch');

        expect(fn () => $this->useCase->handle(OnboardingFixtures::input(phone: OnboardingFixtures::submittedPhone())))
            ->toThrow(OwnerAlreadyHasBusiness::class);
    });

    it('announces nothing when filing the phone number fails', function () {
        ($this->arrangeReads)();

        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->once()->andReturn($this->ownerEvents);
        $this->phones->shouldReceive('attachToBusiness')->once()
            ->andThrow(new RuntimeException('the phone book rejected the row'));

        $this->events->shouldNotReceive('dispatch');

        expect(fn () => $this->useCase->handle(OnboardingFixtures::input(phone: OnboardingFixtures::submittedPhone())))
            ->toThrow(RuntimeException::class, 'the phone book rejected the row');
    });
});

describe('what it deliberately does not depend on', function () {
    it('takes no business context, because it is what brings a tenant into existence', function () {
        $ports = array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            (new ReflectionClass(OnboardBusiness::class))->getConstructor()->getParameters(),
        );

        expect($ports)->toBe([
            BusinessRepository::class,
            IndustryCatalog::class,
            RoleProvisioner::class,
            OwnerRegistrar::class,
            PhoneBook::class,
            SlugAllocator::class,
            PhoneNumberParser::class,
            IdGenerator::class,
            Clock::class,
            TransactionManager::class,
            Dispatcher::class,
        ])->and($ports)->not->toContain(BusinessContext::class);
    });
});
