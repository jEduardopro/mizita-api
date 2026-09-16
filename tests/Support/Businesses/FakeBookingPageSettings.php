<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use App\Domains\Businesses\Contracts\BookingPageSettings;
use App\Domains\Businesses\ValueObjects\BookingPageSnapshot;
use App\Domains\Businesses\ValueObjects\BookingPageStyle;
use Throwable;

final class FakeBookingPageSettings implements BookingPageSettings
{
    /**
     * @var array<string, BookingPageSnapshot>
     */
    private array $pages = [];

    private ?Throwable $applyFailure = null;

    /**
     * @var list<array{businessId: string, accentColor: string, buttonShape: string, theme: string}>
     */
    public array $applications = [];

    /**
     * @var list<string>
     */
    public array $reads = [];

    public function store(string $businessId, BookingPageSnapshot $page): self
    {
        $this->pages[$businessId] = $page;

        return $this;
    }

    public function failingOnApply(Throwable $failure): self
    {
        $this->applyFailure = $failure;

        return $this;
    }

    public function forBusiness(string $businessId): BookingPageSnapshot
    {
        $this->reads[] = $businessId;

        return $this->pages[$businessId] ?? SettingsFixtures::bookingPage();
    }

    public function applyTo(string $businessId, BookingPageStyle $style): void
    {
        if ($this->applyFailure !== null) {
            throw $this->applyFailure;
        }

        $this->applications[] = [
            'businessId' => $businessId,
            'accentColor' => $style->accentColor,
            'buttonShape' => $style->buttonShape,
            'theme' => $style->theme,
        ];

        $this->pages[$businessId] = new BookingPageSnapshot(
            accentColor: $style->accentColor,
            buttonShape: $style->buttonShape,
            theme: $style->theme,
            bannerUrl: null,
            gallery: [],
        );
    }
}
