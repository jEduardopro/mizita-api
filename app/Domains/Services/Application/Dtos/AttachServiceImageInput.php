<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\Dtos;

use App\Domains\Services\Exceptions\ServiceImageTooLarge;
use App\Domains\Services\Exceptions\ServiceNotFound;
use App\Domains\Services\Exceptions\UnsupportedServiceImage;

final readonly class AttachServiceImageInput
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

    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    public function __construct(
        public string $serviceId,
        public string $sourcePath,
        public string $fileName,
        public string $mimeType,
        public int $sizeInBytes,
    ) {}

    /**
     * @throws ServiceNotFound
     * @throws UnsupportedServiceImage
     * @throws ServiceImageTooLarge
     */
    public function validate(): void
    {
        $this->validateServiceId();
        $this->validateSourcePath();
        $this->validateMimeType();
        $this->validateSizeInBytes();
    }

    private function validateServiceId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->serviceId) !== 1) {
            throw ServiceNotFound::withId($this->serviceId);
        }
    }

    private function validateSourcePath(): void
    {
        if (trim($this->sourcePath) === '') {
            throw UnsupportedServiceImage::missing();
        }
    }

    private function validateMimeType(): void
    {
        if (! in_array($this->mimeType, self::ACCEPTED_MIME_TYPES, true)) {
            throw UnsupportedServiceImage::ofType($this->mimeType);
        }
    }

    private function validateSizeInBytes(): void
    {
        if ($this->sizeInBytes <= 0) {
            throw UnsupportedServiceImage::missing();
        }

        if ($this->sizeInBytes > self::MAXIMUM_BYTES) {
            throw ServiceImageTooLarge::atBytes($this->sizeInBytes, self::MAXIMUM_BYTES);
        }
    }
}
