<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\BusinessData;
use App\Domains\Businesses\Application\Dtos\OnboardBusinessInput;
use App\Domains\Businesses\Application\Dtos\PhoneNumberInput;
use App\Domains\Businesses\Application\UseCases\OnboardBusiness;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Contracts\IndustryCatalog;
use App\Domains\Businesses\Contracts\OwnerRegistrar;
use App\Domains\Businesses\Contracts\PaymentMethodProvisioner;
use App\Domains\Businesses\Contracts\PhoneBook;
use App\Domains\Businesses\Contracts\RoleProvisioner;
use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Events\BusinessCreated;
use App\Domains\Businesses\Exceptions\BusinessNameAlreadyTaken;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\Exceptions\BusinessSlugAlreadyTaken;
use App\Domains\Businesses\Exceptions\OwnerAlreadyHasBusiness;
use App\Domains\Businesses\Exceptions\UnknownIndustry;
use App\Domains\Businesses\Services\SlugAllocator;
use App\Shared\Application\UseCaseError;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\PhoneNumberParser;
use App\Shared\Contracts\TransactionManager;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\DomainFailureKind;
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
    $this->paymentMethods = Mockery::mock(PaymentMethodProvisioner::class);
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
            $this->paymentMethods,
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

    $this->answer = fn (OnboardBusinessInput $input, ?OnboardBusiness $useCase = null): UseCaseResponse => (
        $useCase ?? $this->useCase
    )->handle($input);

    $this->onboard = fn (OnboardBusinessInput $input, ?OnboardBusiness $useCase = null): BusinessData => (
        ($this->answer)($input, $useCase)
    )->value();

    $this->refuse = function (OnboardBusinessInput $input, ?OnboardBusiness $useCase = null): UseCaseError {
        $response = ($this->answer)($input, $useCase);

        expect($response->failed())->toBeTrue();

        return $response->error();
    };

    $this->roles->shouldReceive('provisionFor')->byDefault();
    $this->paymentMethods->shouldReceive('provisionFor')->byDefault();

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

        $data = ($this->onboard)(OnboardingFixtures::input());

        expect($data)->toBeInstanceOf(BusinessData::class)
            ->and($data->id)->toBe(OnboardingFixtures::GENERATED_BUSINESS_ID)
            ->and($data->name)->toBe('Barbería Ñandú')
            ->and($data->slug)->toBe('barberia-nandu')
            ->and($data->timezone)->toBe('Europe/Madrid')
            ->and($data->industryId)->toBe(OnboardingFixtures::INDUSTRY_ID)
            ->and($data->logoUrl)->toBeNull()
            ->and($data->createdAt)->toEqual(OnboardingFixtures::now());

        expect($saved)->toBeInstanceOf(Business::class)
            ->and($saved->id)->toBe(OnboardingFixtures::GENERATED_BUSINESS_ID)
            ->and($saved->name())->toBe('Barbería Ñandú')
            ->and($saved->slug())->toBe('barberia-nandu')
            ->and($saved->timezone())->toBe('Europe/Madrid')
            ->and($saved->industryId())->toBe(OnboardingFixtures::INDUSTRY_ID)
            ->and($saved->createdAt)->toEqual(OnboardingFixtures::now());
    });

    it('writes the address the allocator handed it, not the base one', function () {
        ($this->arrangeReads)([OnboardingFixtures::SLUG, 'barberia-nandu-2']);

        $saved = null;
        $this->businesses->shouldReceive('save')->once()->with(Mockery::capture($saved));
        $this->owners->shouldReceive('registerOwner')->andReturn([]);
        $this->events->shouldReceive('dispatch')->once();

        $data = ($this->onboard)(OnboardingFixtures::input());

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

        $data = ($this->onboard)(OnboardingFixtures::input(name: '   Barbería Ñandú   '));

        expect($saved->name())->toBe('Barbería Ñandú')
            ->and($data->name)->toBe('Barbería Ñandú');
    });

    it('stamps the business with the injected clock, never with real time', function () {
        ($this->arrangeReads)();

        $useCase = ($this->build)(clock: new FakeClock(new DateTimeImmutable('2026-03-29T01:30:00+00:00')));

        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->andReturn([]);
        $this->events->shouldReceive('dispatch')->once();

        expect(($this->onboard)(OnboardingFixtures::input(), $useCase)->createdAt)
            ->toEqual(new DateTimeImmutable('2026-03-29T01:30:00+00:00'));
    });

    it('takes the owner from the input rather than from anything the client sent', function () {
        ($this->arrangeReads)();

        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->once()
            ->with(OnboardingFixtures::GENERATED_BUSINESS_ID, 'another-account-uuid')
            ->andReturn([]);
        $this->events->shouldReceive('dispatch')->once();

        ($this->onboard)(OnboardingFixtures::input(ownerAccountId: 'another-account-uuid'));
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

        ($this->onboard)(OnboardingFixtures::input());
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

        ($this->onboard)(OnboardingFixtures::input());

        expect($order)->toBe(['save', 'provision', 'owner']);
    });

    it('provisions them inside the transaction that writes the business', function () {
        ($this->arrangeReads)();

        $this->roles->shouldReceive('provisionFor')->once()
            ->with(Mockery::on($this->recordTransactionState));
        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->andReturn([]);
        $this->events->shouldReceive('dispatch')->once();

        ($this->onboard)(OnboardingFixtures::input());

        expect($this->insideTransaction)->toBe([true]);
    });

    it('provisions nothing when the industry guard refuses the signup', function () {
        $this->roles->shouldNotReceive('provisionFor');
        $this->industries->shouldReceive('exists')->andReturn(false);
        $this->businesses->shouldNotReceive('save');
        $this->owners->shouldNotReceive('registerOwner');
        $this->events->shouldNotReceive('dispatch');

        expect(($this->refuse)(OnboardingFixtures::input())->code)->toBe('unknown_industry');
    });

    it('provisions nothing when the time zone guard refuses the signup', function () {
        ($this->arrangeReads)();

        $this->roles->shouldNotReceive('provisionFor');
        $this->businesses->shouldNotReceive('save');
        $this->owners->shouldNotReceive('registerOwner');
        $this->events->shouldNotReceive('dispatch');

        expect(($this->refuse)(OnboardingFixtures::input(timezone: 'europe/madrid'))->code)
            ->toBe('invalid_timezone');
    });
});

describe('the payment methods the business starts life with', function () {
    it('provisions them for the business it has just created, exactly once', function () {
        ($this->arrangeReads)();

        $this->paymentMethods->shouldReceive('provisionFor')->once()
            ->with(OnboardingFixtures::GENERATED_BUSINESS_ID);
        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->andReturn([]);
        $this->events->shouldReceive('dispatch')->once();

        ($this->onboard)(OnboardingFixtures::input());
    });

    it('provisions them after the roles and before the owner is registered', function () {
        ($this->arrangeReads)();

        $order = [];
        $record = function (string $step) use (&$order): bool {
            $order[] = $step;

            return true;
        };

        $this->businesses->shouldReceive('save')->once()
            ->with(Mockery::on(fn (): bool => $record('save')));
        $this->roles->shouldReceive('provisionFor')->once()
            ->with(Mockery::on(fn (): bool => $record('roles')));
        $this->paymentMethods->shouldReceive('provisionFor')->once()
            ->with(Mockery::on(fn (): bool => $record('payment methods')));
        $this->owners->shouldReceive('registerOwner')->once()
            ->with(Mockery::on(fn (): bool => $record('owner')), Mockery::any())
            ->andReturn([]);
        $this->events->shouldReceive('dispatch')->once();

        ($this->onboard)(OnboardingFixtures::input());

        expect($order)->toBe(['save', 'roles', 'payment methods', 'owner']);
    });

    it('provisions them inside the transaction that writes the business', function () {
        ($this->arrangeReads)();

        $this->paymentMethods->shouldReceive('provisionFor')->once()
            ->with(Mockery::on($this->recordTransactionState));
        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->andReturn([]);
        $this->events->shouldReceive('dispatch')->once();

        ($this->onboard)(OnboardingFixtures::input());

        expect($this->insideTransaction)->toBe([true]);
    });

    it('provisions nothing when the industry guard refuses the signup', function () {
        $this->paymentMethods->shouldNotReceive('provisionFor');
        $this->industries->shouldReceive('exists')->andReturn(false);
        $this->businesses->shouldNotReceive('save');
        $this->owners->shouldNotReceive('registerOwner');
        $this->events->shouldNotReceive('dispatch');

        expect(($this->refuse)(OnboardingFixtures::input())->code)->toBe('unknown_industry');
    });

    it('provisions nothing when the time zone guard refuses the signup', function () {
        ($this->arrangeReads)();

        $this->paymentMethods->shouldNotReceive('provisionFor');
        $this->businesses->shouldNotReceive('save');
        $this->owners->shouldNotReceive('registerOwner');
        $this->events->shouldNotReceive('dispatch');

        expect(($this->refuse)(OnboardingFixtures::input(timezone: 'europe/madrid'))->code)
            ->toBe('invalid_timezone');
    });

    it('provisions nothing when the name is already taken', function () {
        $this->industries->shouldReceive('exists')->andReturn(true);
        $this->businesses->shouldReceive('existsByName')->andReturn(true);

        $this->paymentMethods->shouldNotReceive('provisionFor');
        $this->roles->shouldNotReceive('provisionFor');
        $this->businesses->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        expect(($this->refuse)(OnboardingFixtures::input())->code)->toBe('business_name_taken');
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

        ($this->onboard)(OnboardingFixtures::input());

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

        ($this->onboard)(OnboardingFixtures::input(phone: OnboardingFixtures::submittedPhone()));

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

        ($this->onboard)(OnboardingFixtures::input(phone: OnboardingFixtures::submittedPhone()));

        expect($filed->equals(OnboardingFixtures::phone()))->toBeTrue()
            ->and($filed->e164())->toBe(PhoneNumbers::MX_E164);
    });

    it('asks the parser once, with the country declared and the string as typed', function () {
        $this->phones->shouldReceive('attachToBusiness')->once();

        ($this->onboard)(OnboardingFixtures::input(
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

        ($this->onboard)(OnboardingFixtures::input(
            phone: OnboardingFixtures::submittedPhone($country, $national),
        ));

        expect($filed->equals(OnboardingFixtures::phone($country, $national)))->toBeTrue();
    })->with([
        'mexico' => [CountryCode::Mx, '5512345678'],
        'the united states' => [CountryCode::Us, '2125550147'],
    ]);

    it('never touches the phone book or the parser when no number was given', function () {
        $this->phones->shouldNotReceive('attachToBusiness');

        ($this->onboard)(OnboardingFixtures::input());

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
        $error = ($this->refuse)(
            OnboardingFixtures::input(phone: new PhoneNumberInput($countryCode, '5512345678')),
        );

        expect($error->code)->toBe('unsupported_phone_number')
            ->and($error->cause()->getMessage())
            ->toBe("[{$countryCode}] is not a country this platform operates in.");

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

        $error = ($this->refuse)(
            OnboardingFixtures::input(phone: OnboardingFixtures::submittedPhone(CountryCode::Us, '8421133471')),
            $useCase,
        );

        expect($error->code)->toBe('unsupported_phone_number')
            ->and($error->cause()->getMessage())
            ->toBe('The number offered is not a valid phone number in [US].');

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

        ($this->onboard)(OnboardingFixtures::input());

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

        ($this->onboard)(OnboardingFixtures::input());

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

        ($this->onboard)(OnboardingFixtures::input());
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

    it('lets a domain failure a listener raises after the commit escape, never answering with one', function () {
        ($this->arrangeReads)();
        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->andReturn([]);

        $listenerFailure = BusinessNotFound::withId(OnboardingFixtures::GENERATED_BUSINESS_ID);
        $this->events->shouldReceive('dispatch')->once()->andThrow($listenerFailure);

        try {
            $this->useCase->handle(OnboardingFixtures::input());
            $thrown = null;
        } catch (Throwable $failure) {
            $thrown = $failure;
        }

        expect($thrown)->toBe($listenerFailure)
            ->and($this->transactions->runs())->toBe(1);
    });

    it('stops announcing at the listener that failed, and still never answers with a failure', function () {
        ($this->arrangeReads)();
        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->andReturn($this->ownerEvents);

        $announced = [];
        $this->events->shouldReceive('dispatch')->twice()
            ->with(Mockery::on(function (object $event) use (&$announced): bool {
                $announced[] = $event;

                return true;
            }))
            ->andReturnUsing(function () use (&$announced): void {
                if (count($announced) === 2) {
                    throw BusinessNotFound::withId(OnboardingFixtures::GENERATED_BUSINESS_ID);
                }
            });

        expect(fn () => $this->useCase->handle(OnboardingFixtures::input()))
            ->toThrow(BusinessNotFound::class);

        expect($announced)->toHaveCount(2);
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

        $error = ($this->refuse)(OnboardingFixtures::input(industryId: $retiredIndustryId));

        expect($error->code)->toBe('unknown_industry')
            ->and($error->cause()->getMessage())->toBe("Industry [{$retiredIndustryId}] is not in the catalog.");

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

        $error = ($this->refuse)(OnboardingFixtures::input(industryId: $industryId));

        expect($error->code)->toBe('unknown_industry')
            ->and($error->cause()->getMessage())->toBe("Industry [{$industryId}] is not in the catalog.");

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

        $error = ($this->refuse)(OnboardingFixtures::input());

        expect($error->code)->toBe('business_name_taken')
            ->and($error->cause()->getMessage())->toBe('A business named [Barbería Ñandú] already exists.');

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

        expect(($this->refuse)(OnboardingFixtures::input(name: $name))->code)
            ->toBe('business_name_not_sluggable');

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

        $error = ($this->refuse)(OnboardingFixtures::input(timezone: 'europe/madrid'));

        expect($error->code)->toBe('invalid_timezone')
            ->and($error->cause()->getMessage())
            ->toBe('[europe/madrid] is not a valid IANA time zone identifier.');

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

        $error = ($this->refuse)(OnboardingFixtures::input(phone: OnboardingFixtures::submittedPhone()));

        expect($error->code)->toBe('business_name_taken')
            ->and($error->cause())->toBe($conflict)
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

        $error = ($this->refuse)(OnboardingFixtures::input());

        expect($error->code)->toBe('business_slug_taken')
            ->and($error->cause())->toBe($conflict);
    });

    it('announces nothing when the caller already owns a business', function () {
        ($this->arrangeReads)();

        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->once()
            ->andThrow(OwnerAlreadyHasBusiness::forAccount(OnboardingFixtures::OWNER_ACCOUNT_ID));

        $this->phones->shouldNotReceive('attachToBusiness');
        $this->events->shouldNotReceive('dispatch');

        expect(($this->refuse)(OnboardingFixtures::input(phone: OnboardingFixtures::submittedPhone()))->code)
            ->toBe('owner_already_has_business');
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

describe('the shape of the answer', function () {
    it('succeeds with the business data and no warnings when everything holds', function () {
        ($this->arrangeReads)();
        $this->businesses->shouldReceive('save')->once();
        $this->owners->shouldReceive('registerOwner')->andReturn([]);
        $this->events->shouldReceive('dispatch')->once();

        $response = ($this->answer)(OnboardingFixtures::input());

        expect($response->succeeded())->toBeTrue()
            ->and($response->failed())->toBeFalse()
            ->and($response->warnings())->toBe([])
            ->and($response->value())->toBeInstanceOf(BusinessData::class);
    });

    it('answers with a failure instead of throwing when a domain rule refuses', function () {
        $this->industries->shouldReceive('exists')->andReturn(false);
        $this->businesses->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        $response = ($this->answer)(OnboardingFixtures::input());

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('unknown_industry')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($response->error()->cause())->toBeInstanceOf(UnknownIndustry::class);
    });

    it('classifies a losing race as a conflict, which is the 409 the client sees', function () {
        ($this->arrangeReads)();
        $this->businesses->shouldReceive('save')->once()
            ->andThrow(BusinessSlugAlreadyTaken::for(OnboardingFixtures::SLUG));
        $this->events->shouldNotReceive('dispatch');

        expect(($this->refuse)(OnboardingFixtures::input())->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('keeps the original exception reachable, so the unwrapping caller gets the same instance', function () {
        ($this->arrangeReads)();

        $conflict = BusinessSlugAlreadyTaken::for(OnboardingFixtures::SLUG);
        $this->businesses->shouldReceive('save')->once()->andThrow($conflict);
        $this->events->shouldNotReceive('dispatch');

        $response = ($this->answer)(OnboardingFixtures::input());

        expect(fn () => $response->value())->toThrow($conflict);
    });

    it('lets a programmer error escape rather than dressing it as a domain failure', function () {
        $bug = new RuntimeException('the industry catalog connection went away');
        $this->industries->shouldReceive('exists')->once()->andThrow($bug);
        $this->businesses->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        try {
            $this->useCase->handle(OnboardingFixtures::input());
            $thrown = null;
        } catch (Throwable $failure) {
            $thrown = $failure;
        }

        expect($thrown)->toBe($bug)
            ->and($this->transactions->runs())->toBe(0);
    });

    it('refuses an input the form request never saw, because handle validates before it reads anything', function () {
        $this->industries->shouldNotReceive('exists');
        $this->businesses->shouldNotReceive('existsByName');
        $this->businesses->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        expect(($this->refuse)(OnboardingFixtures::input(ownerAccountId: '   '))->code)
            ->toBe('invalid_business_owner');
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
            PaymentMethodProvisioner::class,
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

    it('takes no port that answers with a use case response, because one inside the transaction would commit', function (string $port) {
        $returnTypes = array_map(
            static fn (ReflectionMethod $method): string => (string) $method->getReturnType(),
            (new ReflectionClass($port))->getMethods(),
        );

        expect($returnTypes)->not->toContain(UseCaseResponse::class);
    })->with([
        'the business repository' => BusinessRepository::class,
        'the industry catalog' => IndustryCatalog::class,
        'the role provisioner' => RoleProvisioner::class,
        'the payment method provisioner' => PaymentMethodProvisioner::class,
        'the owner registrar' => OwnerRegistrar::class,
        'the phone book' => PhoneBook::class,
    ]);
});
