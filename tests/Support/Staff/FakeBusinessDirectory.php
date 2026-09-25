<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Contracts\BusinessDirectory;
use RuntimeException;

final class FakeBusinessDirectory implements BusinessDirectory
{
    /**
     * @var list<string>
     */
    public array $lookups = [];

    /**
     * @param  array<string, string>  $names
     */
    public function __construct(
        private readonly array $names = [],
    ) {}

    public function nameOf(string $businessId): string
    {
        $this->lookups[] = $businessId;

        return $this->names[$businessId]
            ?? throw new RuntimeException("Business [{$businessId}] was not found.");
    }
}
