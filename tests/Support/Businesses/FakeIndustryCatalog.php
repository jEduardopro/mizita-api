<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use App\Domains\Businesses\Contracts\IndustryCatalog;

final class FakeIndustryCatalog implements IndustryCatalog
{
    /**
     * @var list<string>
     */
    private array $known;

    /**
     * @var list<string>
     */
    public array $lookups = [];

    public function __construct(string ...$known)
    {
        $this->known = array_values($known);
    }

    public static function holding(string ...$known): self
    {
        return new self(...$known);
    }

    public static function holdingNothing(): self
    {
        return new self;
    }

    public function exists(string $industryId): bool
    {
        $this->lookups[] = $industryId;

        return in_array($industryId, $this->known, true);
    }

    public function wasConsulted(): bool
    {
        return $this->lookups !== [];
    }
}
