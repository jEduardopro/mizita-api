<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Domains\Businesses\Exceptions\BusinessLogoTooLarge;
use App\Domains\Businesses\Exceptions\UnsupportedBusinessLogo;

final readonly class AttachBusinessLogoInput
{
    public const MAXIMUM_BYTES = 2 * 1024 * 1024;

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
     * @throws UnsupportedBusinessLogo
     * @throws BusinessLogoTooLarge
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
            throw UnsupportedBusinessLogo::missing();
        }
    }

    private function validateMimeType(): void
    {
        if (! in_array($this->mimeType, self::ACCEPTED_MIME_TYPES, true)) {
            throw UnsupportedBusinessLogo::ofType($this->mimeType);
        }
    }

    private function validateSizeInBytes(): void
    {
        if ($this->sizeInBytes <= 0) {
            throw UnsupportedBusinessLogo::missing();
        }

        if ($this->sizeInBytes > self::MAXIMUM_BYTES) {
            throw BusinessLogoTooLarge::atBytes($this->sizeInBytes, self::MAXIMUM_BYTES);
        }
    }
}
