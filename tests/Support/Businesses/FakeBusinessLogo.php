<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use App\Domains\Businesses\Contracts\BusinessLogo;
use Throwable;

final class FakeBusinessLogo implements BusinessLogo
{
    /**
     * @var array<string, string>
     */
    private array $urls = [];

    private ?Throwable $replaceFailure = null;

    private ?Throwable $removeFailure = null;

    /**
     * @var list<array{businessId: string, sourcePath: string, fileName: string}>
     */
    public array $replacements = [];

    /**
     * @var list<string>
     */
    public array $removals = [];

    /**
     * @var list<string>
     */
    public array $reads = [];

    public function store(string $businessId, string $url): self
    {
        $this->urls[$businessId] = $url;

        return $this;
    }

    public function failingOnReplace(Throwable $failure): self
    {
        $this->replaceFailure = $failure;

        return $this;
    }

    public function failingOnRemove(Throwable $failure): self
    {
        $this->removeFailure = $failure;

        return $this;
    }

    public function urlFor(string $businessId): ?string
    {
        $this->reads[] = $businessId;

        return $this->urls[$businessId] ?? null;
    }

    public function replace(string $businessId, string $sourcePath, string $fileName): string
    {
        if ($this->replaceFailure !== null) {
            throw $this->replaceFailure;
        }

        $this->replacements[] = [
            'businessId' => $businessId,
            'sourcePath' => $sourcePath,
            'fileName' => $fileName,
        ];

        return $this->urls[$businessId] = SettingsFixtures::LOGO_URL;
    }

    public function remove(string $businessId): void
    {
        if ($this->removeFailure !== null) {
            throw $this->removeFailure;
        }

        unset($this->urls[$businessId]);

        $this->removals[] = $businessId;
    }
}
