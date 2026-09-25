<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

use App\Domains\Staff\Exceptions\ProfilePhotoTooLarge;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\Exceptions\UnsupportedProfilePhoto;
use App\Domains\Staff\ValueObjects\StaffMemberId;

final readonly class ReplaceTeamMemberPhotoInput
{
    public function __construct(
        public string $staffMemberId,
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
        $this->validateStaffMemberId();
        $this->validateSourcePath();
        $this->validateMimeType();
        $this->validateSizeInBytes();
    }

    private function validateStaffMemberId(): void
    {
        StaffMemberId::fromString($this->staffMemberId);
    }

    private function validateSourcePath(): void
    {
        if (trim($this->sourcePath) === '') {
            throw UnsupportedProfilePhoto::missing();
        }
    }

    private function validateMimeType(): void
    {
        if (! in_array($this->mimeType, ReplaceMyProfilePhotoInput::ACCEPTED_MIME_TYPES, true)) {
            throw UnsupportedProfilePhoto::ofType($this->mimeType);
        }
    }

    private function validateSizeInBytes(): void
    {
        if ($this->sizeInBytes <= 0) {
            throw UnsupportedProfilePhoto::missing();
        }

        if ($this->sizeInBytes > ReplaceMyProfilePhotoInput::MAXIMUM_BYTES) {
            throw ProfilePhotoTooLarge::atBytes($this->sizeInBytes, ReplaceMyProfilePhotoInput::MAXIMUM_BYTES);
        }
    }
}
