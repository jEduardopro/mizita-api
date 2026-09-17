<?php

declare(strict_types=1);

namespace Tests\Support\Services;

use App\Domains\Services\Contracts\ServiceImages;

final class FakeServiceImages implements ServiceImages
{
    private const URL_PREFIX = 'https://cdn.mizita.test/services/';

    /**
     * @var array<string, string>
     */
    private array $urls = [];

    /**
     * @var list<array{businessId: string, serviceId: string}>
     */
    public array $reads = [];

    /**
     * @var list<array{businessId: string, serviceIds: list<string>}>
     */
    public array $batchReads = [];

    /**
     * @var list<array{businessId: string, serviceId: string, sourcePath: string, fileName: string}>
     */
    public array $replaced = [];

    /**
     * @var list<array{businessId: string, serviceId: string}>
     */
    public array $removed = [];

    /**
     * @var list<array{businessId: string, source: string, target: string}>
     */
    public array $copied = [];

    /**
     * @param  array<string, string>  $urlsByServiceId
     */
    public static function of(string $businessId, array $urlsByServiceId): self
    {
        return (new self)->add($businessId, $urlsByServiceId);
    }

    /**
     * @param  array<string, string>  $urlsByServiceId
     */
    public function add(string $businessId, array $urlsByServiceId): self
    {
        foreach ($urlsByServiceId as $serviceId => $url) {
            $this->urls[self::keyFor($businessId, $serviceId)] = $url;
        }

        return $this;
    }

    public static function urlOf(string $serviceId, string $fileName): string
    {
        return self::URL_PREFIX.$serviceId.'/'.$fileName;
    }

    public function urlFor(string $businessId, string $serviceId): ?string
    {
        $this->reads[] = ['businessId' => $businessId, 'serviceId' => $serviceId];

        return $this->urls[self::keyFor($businessId, $serviceId)] ?? null;
    }

    /**
     * @param  list<string>  $serviceIds
     * @return array<string, string>
     */
    public function urlsFor(string $businessId, array $serviceIds): array
    {
        $this->batchReads[] = ['businessId' => $businessId, 'serviceIds' => array_values($serviceIds)];

        $found = [];

        foreach ($serviceIds as $serviceId) {
            $url = $this->urls[self::keyFor($businessId, $serviceId)] ?? null;

            if ($url !== null) {
                $found[$serviceId] = $url;
            }
        }

        return $found;
    }

    public function replace(string $businessId, string $serviceId, string $sourcePath, string $fileName): string
    {
        $this->replaced[] = [
            'businessId' => $businessId,
            'serviceId' => $serviceId,
            'sourcePath' => $sourcePath,
            'fileName' => $fileName,
        ];

        return $this->urls[self::keyFor($businessId, $serviceId)] = self::urlOf($serviceId, $fileName);
    }

    public function remove(string $businessId, string $serviceId): void
    {
        $this->removed[] = ['businessId' => $businessId, 'serviceId' => $serviceId];

        unset($this->urls[self::keyFor($businessId, $serviceId)]);
    }

    public function copy(string $businessId, string $sourceServiceId, string $targetServiceId): void
    {
        $this->copied[] = [
            'businessId' => $businessId,
            'source' => $sourceServiceId,
            'target' => $targetServiceId,
        ];

        $url = $this->urls[self::keyFor($businessId, $sourceServiceId)] ?? null;

        if ($url === null) {
            return;
        }

        $this->urls[self::keyFor($businessId, $targetServiceId)] = $url;
    }

    private static function keyFor(string $businessId, string $serviceId): string
    {
        return $businessId.'|'.$serviceId;
    }
}
