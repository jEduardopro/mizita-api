<?php

declare(strict_types=1);

namespace App\Domains\Customers\Application\Dtos;

use App\Domains\Customers\Exceptions\CustomerNotFound;
use App\Domains\Customers\Exceptions\CustomerPhotoTooLarge;
use App\Domains\Customers\Exceptions\UnsupportedCustomerPhoto;

final readonly class AttachCustomerPhotoInput
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
        public string $customerId,
        public string $sourcePath,
        public string $fileName,
        public string $mimeType,
        public int $sizeInBytes,
    ) {}

    /**
     * @throws CustomerNotFound
     * @throws UnsupportedCustomerPhoto
     * @throws CustomerPhotoTooLarge
     */
    public function validate(): void
    {
        $this->validateCustomerId();
        $this->validateSourcePath();
        $this->validateMimeType();
        $this->validateSizeInBytes();
    }

    private function validateCustomerId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->customerId) !== 1) {
            throw CustomerNotFound::withId($this->customerId);
        }
    }

    private function validateSourcePath(): void
    {
        if (trim($this->sourcePath) === '') {
            throw UnsupportedCustomerPhoto::missing();
        }
    }

    private function validateMimeType(): void
    {
        if (! in_array($this->mimeType, self::ACCEPTED_MIME_TYPES, true)) {
            throw UnsupportedCustomerPhoto::ofType($this->mimeType);
        }
    }

    private function validateSizeInBytes(): void
    {
        if ($this->sizeInBytes <= 0) {
            throw UnsupportedCustomerPhoto::missing();
        }

        if ($this->sizeInBytes > self::MAXIMUM_BYTES) {
            throw CustomerPhotoTooLarge::atBytes($this->sizeInBytes, self::MAXIMUM_BYTES);
        }
    }
}
