<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\AttachBusinessLogoInput;
use App\Domains\Businesses\Exceptions\BusinessLogoTooLarge;
use App\Domains\Businesses\Exceptions\UnsupportedBusinessLogo;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Businesses\SettingsFixtures;

it('accepts an upload the form request would have let through', function () {
    expect(fn () => SettingsFixtures::logo()->validate())->not->toThrow(Throwable::class);
});

it('holds the file the transport handed it', function () {
    $logo = SettingsFixtures::logo();

    expect($logo->sourcePath)->toBe(SettingsFixtures::SOURCE_PATH)
        ->and($logo->fileName)->toBe(SettingsFixtures::FILE_NAME)
        ->and($logo->mimeType)->toBe('image/png')
        ->and($logo->sizeInBytes)->toBe(1024);
});

describe('the file it was pointed at', function () {
    it('refuses an upload that points at nothing', function (string $sourcePath) {
        expect(fn () => SettingsFixtures::logo(sourcePath: $sourcePath)->validate())
            ->toThrow(UnsupportedBusinessLogo::class);
    })->with([
        'empty' => '',
        'whitespace only' => '   ',
        'a tab' => "\t",
    ]);
});

describe('the type of image', function () {
    it('accepts the three types the media library is asked to store', function (string $mimeType) {
        expect(fn () => SettingsFixtures::logo(mimeType: $mimeType)->validate())->not->toThrow(Throwable::class);
    })->with([
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ]);

    it('refuses anything else, because a console command never meets the form request', function (string $mimeType) {
        expect(fn () => SettingsFixtures::logo(mimeType: $mimeType)->validate())
            ->toThrow(UnsupportedBusinessLogo::class);
    })->with([
        'empty' => '',
        'gif' => 'image/gif',
        'svg, which carries script' => 'image/svg+xml',
        'a pdf' => 'application/pdf',
        'a php file' => 'application/x-httpd-php',
        'jpeg in another case' => 'IMAGE/JPEG',
        'jpeg with a charset parameter' => 'image/jpeg; charset=binary',
        'jpg rather than jpeg' => 'image/jpg',
    ]);

    it('states the accepted types as the constant the form request derives its rule from', function () {
        expect(AttachBusinessLogoInput::ACCEPTED_MIME_TYPES)
            ->toBe(['image/jpeg', 'image/png', 'image/webp']);
    });
});

describe('the size of the file', function () {
    it('accepts a file up to two megabytes', function (int $sizeInBytes) {
        expect(fn () => SettingsFixtures::logo(sizeInBytes: $sizeInBytes)->validate())->not->toThrow(Throwable::class);
    })->with([
        'a single byte' => 1,
        'a kilobyte' => 1024,
        'exactly the maximum' => AttachBusinessLogoInput::MAXIMUM_BYTES,
    ]);

    it('refuses a file one byte past the maximum', function () {
        expect(fn () => SettingsFixtures::logo(sizeInBytes: AttachBusinessLogoInput::MAXIMUM_BYTES + 1)->validate())
            ->toThrow(BusinessLogoTooLarge::class);
    });

    it('refuses a file with no bytes in it at all', function (int $sizeInBytes) {
        expect(fn () => SettingsFixtures::logo(sizeInBytes: $sizeInBytes)->validate())
            ->toThrow(UnsupportedBusinessLogo::class);
    })->with([
        'empty' => 0,
        'negative' => -1,
    ]);

    it('states the maximum as the two megabytes the plan settled on', function () {
        expect(AttachBusinessLogoInput::MAXIMUM_BYTES)->toBe(2 * 1024 * 1024)
            ->and(AttachBusinessLogoInput::MAXIMUM_BYTES)->toBe(2097152);
    });
});

it('names the missing file before the type, in the order the transport fills them', function () {
    expect(fn () => SettingsFixtures::logo(sourcePath: '', mimeType: 'application/pdf', sizeInBytes: 0)->validate())
        ->toThrow(UnsupportedBusinessLogo::class, 'No logo was offered.');
});

it('names the type before the size, so an oversized pdf is refused as a pdf', function () {
    expect(fn () => SettingsFixtures::logo(
        mimeType: 'application/pdf',
        sizeInBytes: AttachBusinessLogoInput::MAXIMUM_BYTES + 1,
    )->validate())->toThrow(UnsupportedBusinessLogo::class);
});

it('refuses with failures the responder can classify', function (callable $refuse, string $code) {
    $refusal = null;

    try {
        $refuse();
    } catch (DomainFailure $caught) {
        $refusal = $caught;
    }

    expect($refusal?->errorCode())->toBe($code)
        ->and($refusal?->kind())->toBe(DomainFailureKind::Invalid);
})->with([
    'an unsupported type' => [
        fn () => SettingsFixtures::logo(mimeType: 'image/gif')->validate(),
        'unsupported_business_logo',
    ],
    'a file too large' => [
        fn () => SettingsFixtures::logo(sizeInBytes: AttachBusinessLogoInput::MAXIMUM_BYTES + 1)->validate(),
        'business_logo_too_large',
    ],
]);
