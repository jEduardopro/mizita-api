<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

use App\Domains\Staff\Exceptions\ProfilePhotoTooLarge;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\Exceptions\UnsupportedProfilePhoto;

final readonly class ReplaceMyProfilePhotoInput
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
        public string $accountId,
        public string $sourcePath,
        public string $fileName,
        public string $mimeType,
        public int $sizeInBytes,
    ) {}

    /**
     * @throws StaffMemberNotFound
     * @throws UnsupportedProfilePhoto
     * @throws ProfilePhotoTooLarge
     */
    public function validate(): void
    {
        $this->validateAccountId();
        $this->validateSourcePath();
        $this->validateMimeType();
        $this->validateSizeInBytes();
    }

    private function validateAccountId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->accountId) !== 1) {
            throw StaffMemberNotFound::forAccount($this->accountId);
        }
    }

    private function validateSourcePath(): void
    {
        if (trim($this->sourcePath) === '') {
            throw UnsupportedProfilePhoto::missing();
        }
    }

    private function validateMimeType(): void
    {
        if (! in_array($this->mimeType, self::ACCEPTED_MIME_TYPES, true)) {
            throw UnsupportedProfilePhoto::ofType($this->mimeType);
        }
    }

    private function validateSizeInBytes(): void
    {
        if ($this->sizeInBytes <= 0) {
            throw UnsupportedProfilePhoto::missing();
        }

        if ($this->sizeInBytes > self::MAXIMUM_BYTES) {
            throw ProfilePhotoTooLarge::atBytes($this->sizeInBytes, self::MAXIMUM_BYTES);
        }
    }
}
