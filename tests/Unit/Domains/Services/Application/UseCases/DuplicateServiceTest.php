<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\DuplicateServiceInput;
use App\Domains\Services\Application\Presenters\ServicePresenter;
use App\Domains\Services\Application\UseCases\DuplicateService;
use App\Domains\Services\Entities\Service;
use App\Domains\Services\Events\ServiceCreated;
use App\Domains\Services\Services\BookingLinks;
use App\Domains\Services\Services\CopyNamer;
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
    $this->images = FakeServiceImages::of(FakeBusinessContext::BUSINESS_ID, [
        ServiceFixtures::SERVICE_ID => 'https://cdn.mizita.test/corte.png',
    ]);
    $this->staff = FakeStaffDirectory::of(FakeBusinessContext::BUSINESS_ID, [
        ServiceFixtures::STAFF_ID => 'Ada Lovelace',
        ServiceFixtures::SECOND_STAFF_ID => 'Grace Hopper',
    ]);
    $this->events = Mockery::mock(Dispatcher::class);

    $this->withImages = function (FakeServiceImages $images) {
        $this->images = $images;
        $this->useCase = new DuplicateService(
            $this->services,
            $images,
            new ServicePresenter(
                $this->staff,
                $images,
                new FakeBusinessProfile,
                new BookingLinks(ServiceFixtures::BASE_URL),
            ),
            new SlugAllocator,
            new CopyNamer,
            new FixedIdGenerator(ServiceFixtures::GENERATED_SERVICE_ID),
            new FakeClock(new DateTimeImmutable('2026-03-29T10:00:00+00:00')),
            new FakeBusinessContext,
            $this->events,
        );
    };

    ($this->withImages)($this->images);

    $this->onRecord = function (...$overrides) {
        $this->services->store(ServiceFixtures::service(...$overrides));
    };

    $this->duplicate = fn (?string $name = null, string $serviceId = ServiceFixtures::SERVICE_ID) => $this->useCase
        ->handle(new DuplicateServiceInput($serviceId, $name));

    $this->expectNoAnnouncement = fn () => $this->events->shouldNotReceive('dispatch');
});

describe('duplicating a service', function () {
    it('answers with a copy that carries a new identity, a copied name and its own address', function () {
        $this->events->shouldReceive('dispatch')->once();
        ($this->onRecord)();

        $data = ($this->duplicate)()->value();

        expect($data->id)->toBe(ServiceFixtures::GENERATED_SERVICE_ID)
            ->and($data->name)->toBe('Corte de pelo (Copy)')
            ->and($data->slug)->toBe('corte-de-pelo-copy')
            ->and($data->bookingUrl)->toBe('https://mizita.test/ada-salon/corte-de-pelo-copy')
            ->and($data->createdAt)->toEqual(new DateTimeImmutable('2026-03-29T10:00:00+00:00'));
    });

    it('creates the copy hidden, whatever the original is', function (bool $active) {
        $this->events->shouldReceive('dispatch')->once();
        ($this->onRecord)(active: $active);

        expect(($this->duplicate)()->value()->active)->toBeFalse()
            ->and($this->services->saved[0]->isActive())->toBeFalse();
    })->with(['a visible original' => true, 'a hidden original' => false]);

    it('copies every value the original carries but its identity, name, address and date', function () {
        $this->events->shouldReceive('dispatch')->once();
        ($this->onRecord)(
            description: 'Incluye lavado.',
            durationMinutes: 90,
            bufferMinutes: 15,
            price: '400.00',
            color: ServiceColor::Amber,
            staffIds: [ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID],
        );

        $copy = ($this->duplicate)()->value();

        expect($copy->description)->toBe('Incluye lavado.')
            ->and($copy->durationMinutes)->toBe(90)
            ->and($copy->bufferMinutes)->toBe(15)
            ->and($copy->price)->toBe('400.00')
            ->and($copy->color)->toBe(ServiceColor::Amber)
            ->and(array_map(static fn (object $member): string => $member->id, $copy->staff))
            ->toBe([ServiceFixtures::STAFF_ID, ServiceFixtures::SECOND_STAFF_ID]);
    });

    it('saves the copy and leaves the original where it was', function () {
        $this->events->shouldReceive('dispatch')->once();
        ($this->onRecord)();

        ($this->duplicate)();

        expect($this->services->saved)->toHaveCount(1)
            ->and($this->services->saved[0])->toBeInstanceOf(Service::class)
            ->and($this->services->saved[0]->id)->toBe(ServiceFixtures::GENERATED_SERVICE_ID)
            ->and($this->services->saved[0]->businessId)->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('copies the image of the original onto the copy', function () {
        $this->events->shouldReceive('dispatch')->once();
        ($this->onRecord)();

        $data = ($this->duplicate)()->value();

        expect($this->images->copied)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'source' => ServiceFixtures::SERVICE_ID,
            'target' => ServiceFixtures::GENERATED_SERVICE_ID,
        ]])->and($data->imageUrl)->toBe('https://cdn.mizita.test/corte.png');
    });

    it('copies within the business in context, naming it once for both ends', function () {
        $this->events->shouldReceive('dispatch')->once();
        ($this->onRecord)();

        ($this->duplicate)();

        expect($this->images->copied[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->images->urlFor(FakeBusinessContext::BUSINESS_ID, ServiceFixtures::GENERATED_SERVICE_ID))
            ->toBe('https://cdn.mizita.test/corte.png')
            ->and($this->images->urlFor(ServiceFixtures::OTHER_BUSINESS_ID, ServiceFixtures::GENERATED_SERVICE_ID))
            ->toBeNull();
    });

    it('copies nothing when the file it would read belongs to a neighbouring business', function () {
        $this->events->shouldReceive('dispatch')->once();
        ($this->onRecord)();
        $images = FakeServiceImages::of(ServiceFixtures::OTHER_BUSINESS_ID, [
            ServiceFixtures::SERVICE_ID => 'https://cdn.mizita.test/otro.png',
        ]);
        ($this->withImages)($images);

        $data = ($this->duplicate)()->value();

        expect($data->imageUrl)->toBeNull()
            ->and($images->urlFor(FakeBusinessContext::BUSINESS_ID, ServiceFixtures::GENERATED_SERVICE_ID))
            ->toBeNull()
            ->and($images->urlFor(ServiceFixtures::OTHER_BUSINESS_ID, ServiceFixtures::GENERATED_SERVICE_ID))
            ->toBeNull();
    });

    it('announces the copy once, by its own identity', function () {
        $announced = null;
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::capture($announced));
        ($this->onRecord)();

        ($this->duplicate)();

        expect($announced)->toBeInstanceOf(ServiceCreated::class)
            ->and($announced->id)->toBe(ServiceFixtures::GENERATED_SERVICE_ID);
    });
});

describe('naming the copy', function () {
    it('takes the name the client sent, trimmed', function () {
        $this->events->shouldReceive('dispatch')->once();
        ($this->onRecord)();

        $data = ($this->duplicate)('  Corte de pelo (Copia)  ')->value();

        expect($data->name)->toBe('Corte de pelo (Copia)')
            ->and($data->slug)->toBe('corte-de-pelo-copia');
    });

    it('numbers the copy when a copy of that name already exists', function () {
        $this->events->shouldReceive('dispatch')->once();
        ($this->onRecord)();
        $this->services->withTakenNames('Corte de pelo (Copy)');

        $data = ($this->duplicate)()->value();

        expect($data->name)->toBe('Corte de pelo (Copy) 2')
            ->and($data->slug)->toBe('corte-de-pelo-copy-2');
    });

    it('walks past every numbered copy already on record', function () {
        $this->events->shouldReceive('dispatch')->once();
        ($this->onRecord)();
        $this->services->withTakenNames('Corte de pelo (Copy)', 'Corte de pelo (Copy) 2');

        expect(($this->duplicate)()->value()->name)->toBe('Corte de pelo (Copy) 3');
    });

    it('keeps the fallback name within the length a service name may take', function () {
        $this->events->shouldReceive('dispatch')->once();
        ($this->onRecord)(name: str_repeat('a', 120));

        $name = ($this->duplicate)()->value()->name;

        expect(mb_strlen($name))->toBe(120)
            ->and(str_ends_with($name, ' (Copy)'))->toBeTrue();
    });

    it('numbers the copy address past the ones already taken', function () {
        $this->events->shouldReceive('dispatch')->once();
        ($this->onRecord)();
        $this->services->withTakenSlugs('corte-de-pelo-copy');

        expect(($this->duplicate)()->value()->slug)->toBe('corte-de-pelo-copy-2');
    });
});

describe('refusing to duplicate', function () {
    it('does not duplicate a service that belongs to another business', function () {
        ($this->expectNoAnnouncement)();
        ($this->onRecord)(businessId: ServiceFixtures::OTHER_BUSINESS_ID);

        $response = ($this->duplicate)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('service_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->services->saved)->toBe([])
            ->and($this->images->copied)->toBe([]);
    });

    it('answers not found for a service nobody has', function () {
        ($this->expectNoAnnouncement)();

        expect(($this->duplicate)()->error()->code)->toBe('service_not_found')
            ->and($this->services->saved)->toBe([]);
    });

    it('refuses what the input itself refuses, without touching the repository', function (?string $name, string $serviceId, string $code) {
        ($this->expectNoAnnouncement)();
        ($this->onRecord)();

        $response = ($this->duplicate)($name, $serviceId);

        expect($response->error()->code)->toBe($code)
            ->and($this->services->saved)->toBe([])
            ->and($this->services->businessIdsSeen)->toBe([]);
    })->with([
        'an identifier that cannot be a service' => [null, 'not-a-uuid', 'service_not_found'],
        'a name of one character' => ['A', ServiceFixtures::SERVICE_ID, 'invalid_service_name'],
        'a name too long' => [str_repeat('a', 121), ServiceFixtures::SERVICE_ID, 'invalid_service_name'],
    ]);

    it('looks the original up under the business in context', function () {
        $this->events->shouldReceive('dispatch')->once();
        ($this->onRecord)();

        ($this->duplicate)();

        expect(array_unique($this->services->businessIdsSeen))->toBe([FakeBusinessContext::BUSINESS_ID]);
    });
});
