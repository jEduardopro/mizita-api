<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Application\Dtos;

use App\Domains\BookingPages\Exceptions\BookingPageImageTooLarge;
use App\Domains\BookingPages\Exceptions\UnsupportedBookingPageImage;

final readonly class AttachBookingPageImageInput
{
    public const MAXIMUM_BYTES = 5 * 1024 * 1024;

    /**
     * @var list<string>
     */
    public const ACCEPTED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __construct(
        public string $sourcePath,
        public string $fileName,
        public string $mimeType,
        public int $sizeInBytes,
    ) {}

    /**
     * @throws UnsupportedBookingPageImage
     * @throws BookingPageImageTooLarge
     */
    public function validate(): void
    {
        $this->validateSourcePath();
        $this->validateMimeType();
        $this->validateSizeInBytes();
    }

    private function validateSourcePath(): void
    {
        if (trim($this->sourcePath) === '') {
            throw UnsupportedBookingPageImage::missing();
        }
    }

    private function validateMimeType(): void
    {
        if (! in_array($this->mimeType, self::ACCEPTED_MIME_TYPES, true)) {
            throw UnsupportedBookingPageImage::ofType($this->mimeType);
        }
    }

    private function validateSizeInBytes(): void
    {
        if ($this->sizeInBytes <= 0) {
            throw UnsupportedBookingPageImage::missing();
        }

        if ($this->sizeInBytes > self::MAXIMUM_BYTES) {
            throw BookingPageImageTooLarge::atBytes($this->sizeInBytes, self::MAXIMUM_BYTES);
        }
    }
}
