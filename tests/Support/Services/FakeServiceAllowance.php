<?php

declare(strict_types=1);

namespace Tests\Support\Services;

use App\Domains\Services\Contracts\ServiceAllowance;

final class FakeServiceAllowance implements ServiceAllowance
{
    public const FREE_LIMIT = 3;

    /**
     * @var list<string>
     */
    public array $asked = [];

    private function __construct(private readonly ?int $limit) {}

    public static function free(): self
    {
        return new self(self::FREE_LIMIT);
    }

    public static function unlimited(): self
    {
        return new self(null);
    }

    public static function limitedTo(int $limit): self
    {
        return new self($limit);
    }

    public function activeServiceLimitFor(string $businessId): ?int
    {
        $this->asked[] = $businessId;

        return $this->limit;
    }
}
