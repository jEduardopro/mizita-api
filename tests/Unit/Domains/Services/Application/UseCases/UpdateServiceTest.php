<?php

declare(strict_types=1);

use App\Domains\Services\Application\Dtos\ServiceData;
use App\Domains\Services\Application\Presenters\ServicePresenter;
use App\Domains\Services\Application\UseCases\UpdateService;
use App\Domains\Services\Services\BookingLinks;
use App\Domains\Services\Services\SlugAllocator;
use App\Domains\Services\ValueObjects\ServiceColor;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
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

    $this->useCase = new UpdateService(
        $this->services,
        $this->staff,
        new ServicePresenter(
            $this->staff,
            new FakeServiceImages,
            new FakeBusinessProfile,
            new BookingLinks(ServiceFixtures::BASE_URL),
        ),
        new SlugAllocator,
        new FakeBusinessContext,
    );

    $this->onRecord = function (...$overrides) {
        $this->services->store(ServiceFixtures::service(...$overrides));
    };

    $this->update = fn (...$overrides) => $this->useCase->handle(ServiceFixtures::updateInput(...$overrides));
});

describe('updating a service', function () {
    it('applies every change and answers with the service as it now stands', function () {
        ($this->onRecord)();

        $data = ($this->update)(
            name: 'Corte premium',
            description: '  Sin lavado.  ',
            durationMinutes: 90,
            bufferMinutes: 0,
            price: '300',
            color: 'sand',
            active: false,
            staffIds: [ServiceFixtures::SECOND_STAFF_ID],
        )->value();

        expect($data)->toBeInstanceOf(ServiceData::class)
            ->and($data->id)->toBe(ServiceFixtures::SERVICE_ID)
            ->and($data->name)->toBe('Corte premium')
            ->and($data->slug)->toBe('corte-premium')
            ->and($data->description)->toBe('Sin lavado.')
            ->and($data->durationMinutes)->toBe(90)
            ->and($data->bufferMinutes)->toBe(0)
            ->and($data->price)->toBe('300.00')
            ->and($data->color)->toBe(ServiceColor::Sand)
            ->and($data->active)->toBeFalse()
            ->and($data->staff)->toHaveCount(1)
            ->and($data->staff[0]->name)->toBe('Grace Hopper')
            ->and($data->createdAt)->toEqual(ServiceFixtures::now());
    });

    it('saves the service it changed', function () {
        ($this->onRecord)();

        ($this->update)(price: '10');

        expect($this->services->saved)->toHaveCount(1)
            ->and($this->services->saved[0]->id)->toBe(ServiceFixtures::SERVICE_ID)
            ->and($this->services->saved[0]->price())->toBe('10.00');
    });

    it('clears a description the caller left out', function () {
        ($this->onRecord)();

        expect(($this->update)(description: null)->value()->description)->toBeNull();
    });

    it('clears the staff a caller no longer selects', function () {
        ($this->onRecord)();

        expect(($this->update)(staffIds: [])->value()->staff)->toBe([])
            ->and($this->staff->calls[0]['staffIds'])->toBe([]);
    });
});

describe('the visibility short circuit', function () {
    it('accepts a request that restates the visibility the service already has', function (bool $active) {
        ($this->onRecord)(active: $active);

        $response = ($this->update)(active: $active);

        expect($response->succeeded())->toBeTrue()
            ->and($response->value()->active)->toBe($active)
            ->and($this->services->saved)->toHaveCount(1);
    })->with(['a visible service stays visible' => true, 'a hidden service stays hidden' => false]);

    it('hides a service that was visible', function () {
        ($this->onRecord)(active: true);

        expect(($this->update)(active: false)->value()->active)->toBeFalse();
    });

    it('shows a service that was hidden', function () {
        ($this->onRecord)(active: false);

        expect(($this->update)(active: true)->value()->active)->toBeTrue();
    });
});

describe('the rename short circuit', function () {
    it('checks for a twin only when the name actually changed', function () {
        ($this->onRecord)();

        ($this->update)(name: ServiceFixtures::NAME, price: '500');

        expect($this->services->nameChecks)->toBe([])
            ->and($this->services->slugLookups)->toBe([])
            ->and($this->services->saved[0]->slug())->toBe(ServiceFixtures::SLUG);
    });

    it('treats a name that differs only in case as unchanged', function () {
        ($this->onRecord)();

        $response = ($this->update)(name: 'CORTE DE PELO');

        expect($response->succeeded())->toBeTrue()
            ->and($this->services->nameChecks)->toBe([])
            ->and($this->services->saved[0]->name())->toBe(ServiceFixtures::NAME);
    });

    it('treats a name padded with whitespace as unchanged', function () {
        ($this->onRecord)();

        ($this->update)(name: '  Corte de pelo  ');

        expect($this->services->nameChecks)->toBe([]);
    });

    it('does not refuse a save against the service itself when its name is taken', function () {
        ($this->onRecord)();
        $this->services->withTakenNames(ServiceFixtures::NAME);

        expect(($this->update)()->succeeded())->toBeTrue();
    });

    it('renames and re-addresses the service when the name genuinely changed', function () {
        ($this->onRecord)();

        $data = ($this->update)(name: 'Corte premium')->value();

        expect($data->name)->toBe('Corte premium')
            ->and($data->slug)->toBe('corte-premium')
            ->and($this->services->nameChecks)->toBe(['Corte premium']);
    });

    it('leaves its own address out of the ones the allocator must avoid', function () {
        ($this->onRecord)();
        $this->services->withTakenSlugs(ServiceFixtures::SLUG);

        $data = ($this->update)(name: 'Corte de pelo!')->value();

        expect($data->name)->toBe('Corte de pelo!')
            ->and($data->slug)->toBe(ServiceFixtures::SLUG);
    });

    it('numbers the new address past the ones other services hold', function () {
        ($this->onRecord)();
        $this->services->withTakenSlugs('corte-premium', 'corte-premium-2');

        expect(($this->update)(name: 'Corte premium')->value()->slug)->toBe('corte-premium-3');
    });

    it('refuses a name another service already carries', function () {
        ($this->onRecord)();
        $this->services->withTakenNames('Corte premium');

        $response = ($this->update)(name: 'Corte premium');

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('service_name_taken')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->services->saved)->toBe([]);
    });
});

describe('the business it belongs to', function () {
    it('looks the service up under the business in context, never one a caller could name', function () {
        ($this->onRecord)();

        ($this->update)(name: 'Corte premium');

        expect(array_unique($this->services->businessIdsSeen))->toBe([FakeBusinessContext::BUSINESS_ID])
            ->and($this->staff->lastCall()['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('does not find a service that belongs to another business', function () {
        ($this->onRecord)(businessId: ServiceFixtures::OTHER_BUSINESS_ID);

        $response = ($this->update)();

        expect($response->error()->code)->toBe('service_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->services->saved)->toBe([]);
    });

    it('answers not found for a service nobody has', function () {
        expect(($this->update)()->error()->code)->toBe('service_not_found');
    });
});

describe('refusing to update', function () {
    it('refuses a staff member of another business and saves nothing', function () {
        ($this->onRecord)();

        $response = ($this->update)(staffIds: [ServiceFixtures::FOREIGN_STAFF_ID]);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('unknown_staff_member')
            ->and($response->error()->cause()->getMessage())->not->toContain(ServiceFixtures::FOREIGN_STAFF_ID)
            ->and($this->services->saved)->toBe([]);
    });

    it('refuses what the input itself refuses, without touching the repository', function (array $overrides, string $code) {
        ($this->onRecord)();

        $response = ($this->update)(...$overrides);

        expect($response->error()->code)->toBe($code)
            ->and($this->services->saved)->toBe([])
            ->and($this->services->businessIdsSeen)->toBe([]);
    })->with([
        'an identifier that cannot be a service' => [['serviceId' => 'not-a-uuid'], 'service_not_found'],
        'a blank name' => [['name' => '   '], 'invalid_service_name'],
        'a name with nothing to slug' => [['name' => '???'], 'service_name_not_sluggable'],
        'no duration' => [['durationMinutes' => 0], 'invalid_service_duration'],
        'a negative buffer' => [['bufferMinutes' => -5], 'invalid_service_buffer'],
        'a negative price' => [['price' => '-1'], 'invalid_service_price'],
        'a colour outside the palette' => [['color' => 'rose'], 'invalid_service_color'],
    ]);
});
