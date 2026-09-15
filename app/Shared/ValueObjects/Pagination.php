<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

final readonly class Pagination
{
    public const DEFAULT_PER_PAGE = 20;

    public const MAXIMUM_PER_PAGE = 100;

    private const FIRST_PAGE = 1;

    private const MINIMUM_PER_PAGE = 1;

    private function __construct(
        public int $page,
        public int $perPage,
    ) {}

    public static function of(?int $page, ?int $perPage): self
    {
        return new self(
            page: max(self::FIRST_PAGE, $page ?? self::FIRST_PAGE),
            perPage: min(
                self::MAXIMUM_PER_PAGE,
                max(self::MINIMUM_PER_PAGE, $perPage ?? self::DEFAULT_PER_PAGE),
            ),
        );
    }

    public function offset(): int
    {
        return ($this->page - self::FIRST_PAGE) * $this->perPage;
    }
}
