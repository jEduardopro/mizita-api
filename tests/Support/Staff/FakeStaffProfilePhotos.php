<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Contracts\StaffProfilePhotos;
use App\Domains\Staff\Exceptions\StaffProfileNotFound;

final class FakeStaffProfilePhotos implements StaffProfilePhotos
{
    private const URL_PREFIX = 'https://cdn.mizita.test/staff/';

    /**
     * @var array<string, string>
     */
    private array $urls = [];

    /**
     * @var array<string, true>
     */
    private array $known = [];

    /**
     * @var list<array{businessId: string, profileId: string}>
     */
    public array $reads = [];

    /**
     * @var list<array{businessId: string, profileId: string, sourcePath: string, fileName: string}>
     */
    public array $replacements = [];

    /**
     * @var list<array{businessId: string, profileId: string}>
     */
    public array $removals = [];

    public function __construct(
        private readonly StaffJournal $journal = new StaffJournal,
    ) {}

    public static function urlOf(string $profileId, string $fileName): string
    {
        return self::URL_PREFIX.$profileId.'/'.$fileName;
    }

    public function knows(string $businessId, string ...$profileIds): self
    {
        foreach ($profileIds as $profileId) {
            $this->known[self::keyFor($businessId, $profileId)] = true;
        }

        return $this;
    }

    public function store(string $businessId, string $profileId, string $url): self
    {
        $this->knows($businessId, $profileId);
        $this->urls[self::keyFor($businessId, $profileId)] = $url;

        return $this;
    }

    public function urlFor(string $businessId, string $profileId): ?string
    {
        $this->reads[] = ['businessId' => $businessId, 'profileId' => $profileId];

        return $this->urls[self::keyFor($businessId, $profileId)] ?? null;
    }

    public function replace(string $businessId, string $profileId, string $sourcePath, string $fileName): void
    {
        $this->failUnlessKnown($businessId, $profileId);

        $this->journal->record('photos.replace');
        $this->replacements[] = [
            'businessId' => $businessId,
            'profileId' => $profileId,
            'sourcePath' => $sourcePath,
            'fileName' => $fileName,
        ];

        $this->urls[self::keyFor($businessId, $profileId)] = self::urlOf($profileId, $fileName);
    }

    public function remove(string $businessId, string $profileId): void
    {
        $this->failUnlessKnown($businessId, $profileId);

        $this->journal->record('photos.remove');
        $this->removals[] = ['businessId' => $businessId, 'profileId' => $profileId];

        unset($this->urls[self::keyFor($businessId, $profileId)]);
    }

    /**
     * @throws StaffProfileNotFound
     */
    private function failUnlessKnown(string $businessId, string $profileId): void
    {
        if (! isset($this->known[self::keyFor($businessId, $profileId)])) {
            throw StaffProfileNotFound::withId($profileId);
        }
    }

    private static function keyFor(string $businessId, string $profileId): string
    {
        return $businessId.'|'.$profileId;
    }
}
