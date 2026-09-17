<?php

declare(strict_types=1);

namespace Tests\Support\Customers;

use App\Domains\Customers\Contracts\CustomerPhotos;
use App\Domains\Customers\Exceptions\CustomerNotFound;

final class FakeCustomerPhotos implements CustomerPhotos
{
    private const URL_PREFIX = 'https://cdn.mizita.test/customers/';

    /**
     * @var array<string, string>
     */
    private array $urls = [];

    /**
     * @var array<string, true>
     */
    private array $known = [];

    /**
     * @var list<array{businessId: string, customerId: string}>
     */
    public array $reads = [];

    /**
     * @var list<array{businessId: string, customerIds: list<string>}>
     */
    public array $batchReads = [];

    /**
     * @var list<array{businessId: string, customerId: string, sourcePath: string, fileName: string}>
     */
    public array $replacements = [];

    /**
     * @var list<array{businessId: string, customerId: string}>
     */
    public array $removals = [];

    public function __construct(
        public readonly CustomerJournal $journal = new CustomerJournal,
    ) {}

    public static function urlOf(string $customerId, string $fileName): string
    {
        return self::URL_PREFIX.$customerId.'/'.$fileName;
    }

    public function knows(string $businessId, string ...$customerIds): self
    {
        foreach ($customerIds as $customerId) {
            $this->known[self::keyFor($businessId, $customerId)] = true;
        }

        return $this;
    }

    public function store(string $businessId, string $customerId, string $url): self
    {
        $this->knows($businessId, $customerId);
        $this->urls[self::keyFor($businessId, $customerId)] = $url;

        return $this;
    }

    public function urlFor(string $businessId, string $customerId): ?string
    {
        $this->journal->record('photos.forCustomer');
        $this->reads[] = ['businessId' => $businessId, 'customerId' => $customerId];

        return $this->urls[self::keyFor($businessId, $customerId)] ?? null;
    }

    /**
     * @param  list<string>  $customerIds
     * @return array<string, string>
     */
    public function urlsFor(string $businessId, array $customerIds): array
    {
        $this->journal->record('photos.forCustomers');
        $this->batchReads[] = ['businessId' => $businessId, 'customerIds' => array_values($customerIds)];

        $found = [];

        foreach ($customerIds as $customerId) {
            $url = $this->urls[self::keyFor($businessId, $customerId)] ?? null;

            if ($url !== null) {
                $found[$customerId] = $url;
            }
        }

        return $found;
    }

    public function replace(string $businessId, string $customerId, string $sourcePath, string $fileName): void
    {
        $this->failUnlessKnown($businessId, $customerId);

        $this->journal->record('photos.replace');
        $this->replacements[] = [
            'businessId' => $businessId,
            'customerId' => $customerId,
            'sourcePath' => $sourcePath,
            'fileName' => $fileName,
        ];

        $this->urls[self::keyFor($businessId, $customerId)] = self::urlOf($customerId, $fileName);
    }

    public function remove(string $businessId, string $customerId): void
    {
        $this->failUnlessKnown($businessId, $customerId);

        $this->journal->record('photos.remove');
        $this->removals[] = ['businessId' => $businessId, 'customerId' => $customerId];

        unset($this->urls[self::keyFor($businessId, $customerId)]);
    }

    /**
     * @throws CustomerNotFound
     */
    private function failUnlessKnown(string $businessId, string $customerId): void
    {
        if (! isset($this->known[self::keyFor($businessId, $customerId)])) {
            throw CustomerNotFound::withId($customerId);
        }
    }

    private static function keyFor(string $businessId, string $customerId): string
    {
        return $businessId.'|'.$customerId;
    }
}
