<?php

declare(strict_types=1);

use App\Domains\Businesses\Infrastructure\Http\Requests\UploadBusinessLogoRequest;
use Tests\Support\Shared\ImageFiles;
use Tests\Support\Shared\UploadValidation;

/**
 * @return list<string>
 */
function businessLogoUploadFailures(string $sourcePath): array
{
    return UploadValidation::failedRules(new UploadBusinessLogoRequest, 'logo', $sourcePath);
}

it('accepts an image of exactly the largest width or height it serves', function (Closure $image) {
    expect(businessLogoUploadFailures($image()))->toBe([]);
})->with([
    'four thousand ninety six pixels wide' => static fn (): string => ImageFiles::png(4096, 1),
    'four thousand ninety six pixels tall' => static fn (): string => ImageFiles::png(1, 4096),
]);

it('refuses an image one pixel past the largest width or height, on its dimensions alone', function (Closure $image) {
    expect(businessLogoUploadFailures($image()))->toBe(['Dimensions']);
})->with([
    'too wide' => static fn (): string => ImageFiles::png(4097, 1),
    'too tall' => static fn (): string => ImageFiles::png(1, 4097),
]);

it('accepts every image format it allows', function (Closure $image) {
    expect(businessLogoUploadFailures($image()))->toBe([]);
})->with([
    'jpeg' => ImageFiles::jpeg(...),
    'png' => ImageFiles::png(...),
    'webp' => ImageFiles::webp(...),
]);

it('refuses content that is not an allowed image, whatever the client named it', function (Closure $source) {
    expect(businessLogoUploadFailures($source()))->toContain('Mimetypes');
})->with([
    'plain text' => ImageFiles::text(...),
    'a gif' => ImageFiles::gif(...),
]);
