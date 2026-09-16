<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Domains\Businesses\Exceptions\InvalidBusinessAbout;
use App\Domains\Businesses\Exceptions\InvalidBusinessName;
use App\Domains\Businesses\Exceptions\InvalidBusinessSlug;
use App\Domains\Businesses\Exceptions\UnknownIndustry;
use App\Domains\Businesses\ValueObjects\About;
use App\Domains\Businesses\ValueObjects\Slug;

final readonly class BrandDetailsInput
{
    public const MINIMUM_NAME_LENGTH = 2;

    public const MAXIMUM_NAME_LENGTH = 120;

    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';

    public function __construct(
        public string $name,
        public string $slug,
        public string $industryId,
        public ?string $about,
    ) {}

    /**
     * @throws InvalidBusinessName
     * @throws InvalidBusinessSlug
     * @throws UnknownIndustry
     * @throws InvalidBusinessAbout
     */
    public function validate(): void
    {
        $this->validateName();
        $this->validateSlug();
        $this->validateIndustryId();
        $this->validateAbout();
    }

    private function validateName(): void
    {
        $name = trim($this->name);

        if ($name === '') {
            throw InvalidBusinessName::empty();
        }

        if (mb_strlen($name) < self::MINIMUM_NAME_LENGTH) {
            throw InvalidBusinessName::tooShort($name);
        }

        if (mb_strlen($name) > self::MAXIMUM_NAME_LENGTH) {
            throw InvalidBusinessName::tooLong($name);
        }
    }

    private function validateSlug(): void
    {
        Slug::fromString(trim($this->slug));
    }

    private function validateIndustryId(): void
    {
        if (preg_match(self::UUID_PATTERN, $this->industryId) !== 1) {
            throw UnknownIndustry::withId($this->industryId);
        }
    }

    private function validateAbout(): void
    {
        About::fromNullable($this->about);
    }
}
