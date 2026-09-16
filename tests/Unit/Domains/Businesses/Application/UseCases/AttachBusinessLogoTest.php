<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Presenters\BusinessSettingsPresenter;
use App\Domains\Businesses\Application\UseCases\AttachBusinessLogo;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\Exceptions\UnsupportedBusinessLogo;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Businesses\FakeBookingPageSettings;
use Tests\Support\Businesses\FakeBusinessAddressBook;
use Tests\Support\Businesses\FakeBusinessLinkList;
use Tests\Support\Businesses\FakeBusinessLogo;
use Tests\Support\Businesses\FakeBusinessPhoneBook;
use Tests\Support\Businesses\FakeBusinessRepository;
use Tests\Support\Businesses\FakeBusinessSchedule;
use Tests\Support\Businesses\OnboardingFixtures;
use Tests\Support\Businesses\SettingsFixtures;
use Tests\Support\FakeBusinessContext;

beforeEach(function () {
    $this->businesses = (new FakeBusinessRepository)
        ->store(OnboardingFixtures::business(id: FakeBusinessContext::BUSINESS_ID));
    $this->addresses = new FakeBusinessAddressBook;
    $this->links = new FakeBusinessLinkList;
    $this->schedule = new FakeBusinessSchedule;
    $this->bookingPages = new FakeBookingPageSettings;
    $this->phones = new FakeBusinessPhoneBook;
    $this->logo = new FakeBusinessLogo;

    $this->useCase = new AttachBusinessLogo(
        $this->logo,
        new BusinessSettingsPresenter(
            $this->businesses,
            $this->addresses,
            $this->links,
            $this->schedule,
            $this->bookingPages,
            $this->phones,
            $this->logo,
        ),
        new FakeBusinessContext,
    );

    $this->attach = fn (...$overrides) => $this->useCase->handle(SettingsFixtures::logo(...$overrides));
});

it('hands the file to the media port and answers with the settings that now carry a logo', function () {
    $response = ($this->attach)();

    expect($response->succeeded())->toBeTrue()
        ->and($response->value()->logoUrl)->toBe(SettingsFixtures::LOGO_URL)
        ->and($this->logo->replacements)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'sourcePath' => SettingsFixtures::SOURCE_PATH,
            'fileName' => SettingsFixtures::FILE_NAME,
        ]]);
});

it('attaches the logo to the business in context, never to one a caller could name', function () {
    ($this->attach)();

    expect($this->logo->replacements[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
        ->and($this->businesses->idsRead)->toBe([FakeBusinessContext::BUSINESS_ID]);
});

it('replaces the logo the business already had, rather than keeping both', function () {
    $this->logo->store(FakeBusinessContext::BUSINESS_ID, 'https://mizita.test/media/0/old.png');

    expect(($this->attach)()->value()->logoUrl)->toBe(SettingsFixtures::LOGO_URL)
        ->and($this->logo->replacements)->toHaveCount(1);
});

it('refuses what the input itself refuses, without touching the media library', function (array $overrides, string $code) {
    $response = ($this->attach)(...$overrides);

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe($code)
        ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
        ->and($this->logo->replacements)->toBe([])
        ->and($this->businesses->idsRead)->toBe([]);
})->with([
    'no file at all' => [['sourcePath' => ''], 'unsupported_business_logo'],
    'a type the library does not store' => [['mimeType' => 'image/gif'], 'unsupported_business_logo'],
    'an empty file' => [['sizeInBytes' => 0], 'unsupported_business_logo'],
    'a file past two megabytes' => [['sizeInBytes' => 2 * 1024 * 1024 + 1], 'business_logo_too_large'],
]);

it('answers with a failure when the media port cannot find the business', function () {
    $this->logo->failingOnReplace(BusinessNotFound::withId(FakeBusinessContext::BUSINESS_ID));

    $response = ($this->attach)();

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('business_not_found')
        ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
});

it('describes nothing when the file was refused', function () {
    $response = ($this->attach)(mimeType: 'application/pdf');

    expect(fn () => $response->value())->toThrow(UnsupportedBusinessLogo::class)
        ->and($this->businesses->idsRead)->toBe([]);
});
