<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\ServiceData;
use App\Domains\Services\Application\Presenters\ServicePresenter;
use App\Domains\Services\Application\UseCases\CreateService;
use App\Domains\Services\Entities\Service;
use App\Domains\Services\Events\ServiceCreated;
use App\Domains\Services\Exceptions\ServiceSlugAlreadyTaken;
use App\Domains\Services\Services\BookingLinks;
use App\Domains\Services\Services\SlugAllocator;
use App\Domains\Services\ValueObjects\ServiceColor;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;
use Tests\Support\Services\FakeBusinessProfile;
use Tests\Support\Services\FakeServiceImages;
use Tests\Support\Services\FakeServiceRepository;
use Tests\Support\Services\FakeStaffDirectory;
use Tests\Support\Services\ServiceFixtures;

beforeEach(function () {
    $this->services = new FakeServiceRepository;
    $this->staff = FakeStaffDirectory::of(FakeBusinessContext::BUSINESS_ID, [
        ServiceFixtures::STAFF_ID => 'Ada Lovelace',
        ServiceFixtures::SECOND_STAFF_ID => 'Grace Hopper',
    ])->add(ServiceFixtures::OTHER_BUSINESS_ID, [
        ServiceFixtures::FOREIGN_STAFF_ID => 'Katherine Johnson',
    ]);
    $this->events = Mockery::mock(Dispatcher::class);

    $this->useCase = new CreateService(
        $this->services,
        $this->staff,
        new ServicePresenter(
            $this->staff,
            new FakeServiceImages,
            new FakeBusinessProfile,
            new BookingLinks(ServiceFixtures::BASE_URL),
        ),
        new SlugAllocator,
        new FixedIdGenerator(ServiceFixtures::GENERATED_SERVICE_ID),
        new FakeClock(ServiceFixtures::now()),
        new FakeBusinessContext,
        $this->events,
    );

    $this->create = fn (...$overrides) => $this->useCase->handle(ServiceFixtures::createInput(...$overrides));

    $this->expectNoAnnouncement = fn () => $this->events->shouldNotReceive('dispatch');
});

describe('creating a service', function () {
    it('persists the service and answers with its data', function () {
        $this->events->shouldReceive('dispatch')->once();

        $data = ($this->create)()->value();

        expect($data)->toBeInstanceOf(ServiceData::class)
            ->and($data->id)->toBe(ServiceFixtures::GENERATED_SERVICE_ID)
            ->and($data->name)->toBe(ServiceFixtures::NAME)
            ->and($data->slug)->toBe(ServiceFixtures::SLUG)
            ->and($data->description)->toBe('Incluye lavado.')
            ->and($data->durationMinutes)->toBe(45)
            ->and($data->bufferMinutes)->toBe(10)
            ->and($data->price)->toBe('250.00')
            ->and($data->color)->toBe(ServiceColor::Teal)
            ->and($data->active)->toBeTrue()
            ->and($data->imageUrl)->toBeNull()
            ->and($data->bookingUrl)->toBe('https://mizita.test/b/ada-salon/corte-de-pelo')
            ->and($data->staff)->toHaveCount(1)
            ->and($data->staff[0]->name)->toBe('Ada Lovelace')
            ->and($data->createdAt)->toEqual(ServiceFixtures::now());
    });

    it('saves the service the identity and the clock handed it', function () {
        $this->events->shouldReceive('dispatch')->once();

        ($this->create)();

        expect($this->services->saved)->toHaveCount(1);

        $saved = $this->services->saved[0];

        expect($saved)->toBeInstanceOf(Service::class)
            ->and($saved->id)->toBe(ServiceFixtures::GENERATED_SERVICE_ID)
            ->and($saved->name())->toBe(ServiceFixtures::NAME)
            ->and($saved->slug())->toBe(ServiceFixtures::SLUG)
            ->and($saved->staffIds())->toBe([ServiceFixtures::STAFF_ID])
            ->and($saved->createdAt)->toEqual(ServiceFixtures::now());
    });

    it('trims the name before it stores it and before it checks for a twin', function () {
        $this->events->shouldReceive('dispatch')->once();

        ($this->create)(name: '  Corte de pelo  ');

        expect($this->services->saved[0]->name())->toBe(ServiceFixtures::NAME)
            ->and($this->services->nameChecks)->toBe([ServiceFixtures::NAME]);
    });

    it('creates a hidden service when that is what was asked for', function () {
        $this->events->shouldReceive('dispatch')->once();

        expect(($this->create)(active: false)->value()->active)->toBeFalse()
            ->and($this->services->saved[0]->isActive())->toBeFalse();
    });

    it('numbers the address when the base one is taken', function () {
        $this->events->shouldReceive('dispatch')->once();
        $this->services->withTakenSlugs(ServiceFixtures::SLUG, ServiceFixtures::SLUG.'-2');

        expect(($this->create)()->value()->slug)->toBe('corte-de-pelo-3');
    });
});

describe('the business it belongs to', function () {
    it('scopes the service to the business in context, never to one a caller could name', function () {
        $this->events->shouldReceive('dispatch')->once();

        $data = ($this->create)();

        expect($this->services->saved[0]->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($data->succeeded())->toBeTrue();
    });

    it('asks every collaborator about the business in context', function () {
        $this->events->shouldReceive('dispatch')->once();

        ($this->create)();

        expect(array_unique($this->services->businessIdsSeen))->toBe([FakeBusinessContext::BUSINESS_ID])
            ->and($this->staff->lastCall()['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID);
    });
});

describe('the staff it may assign', function () {
    it('assigns the staff of its own business', function () {
        $this->events->shouldReceive('dispatch')->once();

        $data = ($this->create)(staffIds: [ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID]);

        expect($data->value()->staff)->toHaveCount(2)
            ->and($this->services->saved[0]->staffIds())
            ->toBe([ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID]);
    });

    it('refuses a staff member of another business, and says nothing about whose', function () {
        ($this->expectNoAnnouncement)();

        $response = ($this->create)(staffIds: [ServiceFixtures::STAFF_ID, ServiceFixtures::FOREIGN_STAFF_ID]);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('unknown_staff_member')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($response->error()->cause()->getMessage())->not->toContain(ServiceFixtures::FOREIGN_STAFF_ID)
            ->and($this->services->saved)->toBe([]);
    });

    it('refuses a staff member nobody has', function () {
        ($this->expectNoAnnouncement)();

        $response = ($this->create)(staffIds: ['01930000-0000-7000-8000-0000000000ff']);

        expect($response->error()->code)->toBe('unknown_staff_member')
            ->and($this->services->saved)->toBe([]);
    });

    it('asks the directory nothing when no staff was selected', function () {
        $this->events->shouldReceive('dispatch')->once();

        ($this->create)(staffIds: []);

        expect($this->staff->calls)->toHaveCount(1)
            ->and($this->staff->lastCall()['staffIds'])->toBe([])
            ->and($this->services->saved[0]->staffIds())->toBe([]);
    });

    it('counts a duplicated selection once', function () {
        $this->events->shouldReceive('dispatch')->once();

        ($this->create)(staffIds: [ServiceFixtures::STAFF_ID, ServiceFixtures::STAFF_ID]);

        expect($this->services->saved[0]->staffIds())->toBe([ServiceFixtures::STAFF_ID]);
    });
});

describe('refusing to create', function () {
    it('refuses a name another service already carries, in whatever case', function (string $taken) {
        ($this->expectNoAnnouncement)();
        $this->services->withTakenNames($taken);

        $response = ($this->create)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('service_name_taken')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->services->saved)->toBe([]);
    })->with([
        'the same name' => ServiceFixtures::NAME,
        'the same name in another case' => 'CORTE DE PELO',
    ]);

    it('refuses what the input itself refuses, without touching the repository', function (array $overrides, string $code) {
        ($this->expectNoAnnouncement)();

        $response = ($this->create)(...$overrides);

        expect($response->error()->code)->toBe($code)
            ->and($this->services->saved)->toBe([])
            ->and($this->services->nameChecks)->toBe([]);
    })->with([
        'a blank name' => [['name' => '   '], 'invalid_service_name'],
        'a name with nothing to slug' => [['name' => '!!!!'], 'service_name_not_sluggable'],
        'no duration' => [['durationMinutes' => 0], 'invalid_service_duration'],
        'a negative buffer' => [['bufferMinutes' => -5], 'invalid_service_buffer'],
        'a price in scientific notation' => [['price' => '1e3'], 'invalid_service_price'],
        'a colour outside the palette' => [['color' => 'rose'], 'invalid_service_color'],
    ]);

    it('announces nothing when the save itself fails', function () {
        ($this->expectNoAnnouncement)();
        $this->services->failingOnSave(ServiceSlugAlreadyTaken::for(ServiceFixtures::SLUG));

        $response = ($this->create)();

        expect($response->error()->code)->toBe('service_slug_taken')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });
});

describe('announcing the service', function () {
    it('announces the service once, by the identity it was given', function () {
        $announced = null;
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::capture($announced));

        ($this->create)();

        expect($announced)->toBeInstanceOf(ServiceCreated::class)
            ->and($announced->id)->toBe(ServiceFixtures::GENERATED_SERVICE_ID);
    });

    it('announces the service only once it is on record', function () {
        $savedWhenAnnounced = null;
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::on(
            function () use (&$savedWhenAnnounced): bool {
                $savedWhenAnnounced = count($this->services->saved);

                return true;
            },
        ));

        ($this->create)();

        expect($savedWhenAnnounced)->toBe(1);
    });
});
