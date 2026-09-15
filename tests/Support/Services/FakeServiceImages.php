<?php

declare(strict_types=1);

namespace Tests\Support\Services;

use App\Domains\Services\Contracts\ServiceImages;

final class FakeServiceImages implements ServiceImages
{
    private const URL_PREFIX = 'https://cdn.mizita.test/services/';

    /**
     * @var list<array{serviceId: string, sourcePath: string, fileName: string}>
     */
    public array $replaced = [];

    /**
     * @var list<string>
     */
    public array $removed = [];

    /**
     * @var list<array{source: string, target: string}>
     */
    public array $copied = [];

    /**
     * @var list<string>
     */
    public array $urlForCalls = [];

    /**
     * @var list<list<string>>
     */
    public array $urlsForCalls = [];

    /**
     * @param  array<string, string>  $urls
     */
    public function __construct(private array $urls = []) {}

    public function urlFor(string $serviceId): ?string
    {
        $this->urlForCalls[] = $serviceId;

        return $this->urls[$serviceId] ?? null;
    }

    /**
     * @param  list<string>  $serviceIds
     * @return array<string, string>
     */
    public function urlsFor(array $serviceIds): array
    {
        $this->urlsForCalls[] = array_values($serviceIds);

        $urls = [];

        foreach ($serviceIds as $serviceId) {
            if (isset($this->urls[$serviceId])) {
                $urls[$serviceId] = $this->urls[$serviceId];
            }
        }

        return $urls;
    }

    public function replace(string $serviceId, string $sourcePath, string $fileName): string
    {
        $this->replaced[] = [
            'serviceId' => $serviceId,
            'sourcePath' => $sourcePath,
            'fileName' => $fileName,
        ];

        return $this->urls[$serviceId] = self::URL_PREFIX.$serviceId.'/'.$fileName;
    }

    public function remove(string $serviceId): void
    {
        $this->removed[] = $serviceId;

        unset($this->urls[$serviceId]);
    }

    public function copy(string $sourceServiceId, string $targetServiceId): void
    {
        $this->copied[] = ['source' => $sourceServiceId, 'target' => $targetServiceId];

        if (isset($this->urls[$sourceServiceId])) {
            $this->urls[$targetServiceId] = $this->urls[$sourceServiceId];
        }
    }
}
