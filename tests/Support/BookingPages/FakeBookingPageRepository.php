<?php

declare(strict_types=1);

namespace Tests\Support\BookingPages;

use App\Domains\BookingPages\Contracts\BookingPageRepository;
use App\Domains\BookingPages\Entities\BookingPage;
use App\Domains\BookingPages\Exceptions\BookingPageNotFound;

final class FakeBookingPageRepository implements BookingPageRepository
{
    /**
     * @var array<string, BookingPage>
     */
    private array $pages = [];

    /**
     * @var list<BookingPage>
     */
    public array $saved = [];

    /**
     * @var list<string>
     */
    public array $businessIdsSeen = [];

    public function store(BookingPage ...$pages): self
    {
        foreach ($pages as $page) {
            $this->pages[$page->businessId] = $page;
        }

        return $this;
    }

    public function findForBusiness(string $businessId): ?BookingPage
    {
        $this->businessIdsSeen[] = $businessId;

        return $this->pages[$businessId] ?? null;
    }

    public function ofBusiness(string $businessId): BookingPage
    {
        $this->businessIdsSeen[] = $businessId;

        return $this->pages[$businessId] ?? throw BookingPageNotFound::forBusiness($businessId);
    }

    public function save(BookingPage $page): void
    {
        $this->pages[$page->businessId] = $page;
        $this->saved[] = $page;
    }
}
