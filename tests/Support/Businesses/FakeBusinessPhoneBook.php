<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use App\Domains\Businesses\Contracts\PhoneBook;
use App\Shared\ValueObjects\PhoneNumber;
use Throwable;

final class FakeBusinessPhoneBook implements PhoneBook
{
    /**
     * @var array<string, PhoneNumber>
     */
    private array $phones = [];

    private ?Throwable $replaceFailure = null;

    /**
     * @var list<array{businessId: string, phone: PhoneNumber}>
     */
    public array $attachments = [];

    /**
     * @var list<array{businessId: string, phone: ?PhoneNumber}>
     */
    public array $replacements = [];

    /**
     * @var list<string>
     */
    public array $reads = [];

    public function store(string $businessId, PhoneNumber $phone): self
    {
        $this->phones[$businessId] = $phone;

        return $this;
    }

    public function failingOnReplace(Throwable $failure): self
    {
        $this->replaceFailure = $failure;

        return $this;
    }

    public function forBusiness(string $businessId): ?PhoneNumber
    {
        $this->reads[] = $businessId;

        return $this->phones[$businessId] ?? null;
    }

    public function attachToBusiness(string $businessId, PhoneNumber $phone): void
    {
        $this->phones[$businessId] = $phone;
        $this->attachments[] = ['businessId' => $businessId, 'phone' => $phone];
    }

    public function replaceForBusiness(string $businessId, ?PhoneNumber $phone): void
    {
        if ($this->replaceFailure !== null) {
            throw $this->replaceFailure;
        }

        if ($phone === null) {
            unset($this->phones[$businessId]);
        } else {
            $this->phones[$businessId] = $phone;
        }

        $this->replacements[] = ['businessId' => $businessId, 'phone' => $phone];
    }
}
