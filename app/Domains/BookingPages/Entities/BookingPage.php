<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Entities;

use App\Domains\BookingPages\ValueObjects\BrandColor;
use App\Domains\BookingPages\ValueObjects\ButtonShape;
use App\Domains\BookingPages\ValueObjects\PageTheme;
use DateTimeImmutable;

final class BookingPage
{
    public const DEFAULT_ACCENT_COLOR = BrandColor::Ink;

    public const DEFAULT_BUTTON_SHAPE = ButtonShape::Pill;

    public const DEFAULT_THEME = PageTheme::Light;

    private function __construct(
        public readonly string $id,
        public readonly string $businessId,
        private BrandColor $accentColor,
        private ButtonShape $buttonShape,
        private PageTheme $theme,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        string $id,
        string $businessId,
        BrandColor $accentColor,
        ButtonShape $buttonShape,
        PageTheme $theme,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            accentColor: $accentColor,
            buttonShape: $buttonShape,
            theme: $theme,
            createdAt: $now,
        );
    }

    public static function withDefaults(string $id, string $businessId, DateTimeImmutable $now): self
    {
        return self::create(
            id: $id,
            businessId: $businessId,
            accentColor: self::DEFAULT_ACCENT_COLOR,
            buttonShape: self::DEFAULT_BUTTON_SHAPE,
            theme: self::DEFAULT_THEME,
            now: $now,
        );
    }

    public static function restore(
        string $id,
        string $businessId,
        BrandColor $accentColor,
        ButtonShape $buttonShape,
        PageTheme $theme,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            accentColor: $accentColor,
            buttonShape: $buttonShape,
            theme: $theme,
            createdAt: $createdAt,
        );
    }

    public function restyle(BrandColor $accentColor, ButtonShape $buttonShape, PageTheme $theme): void
    {
        $this->changeAccent($accentColor);
        $this->changeButtonShape($buttonShape);
        $this->changeTheme($theme);
    }

    public function changeAccent(BrandColor $accentColor): void
    {
        $this->accentColor = $accentColor;
    }

    public function changeButtonShape(ButtonShape $buttonShape): void
    {
        $this->buttonShape = $buttonShape;
    }

    public function changeTheme(PageTheme $theme): void
    {
        $this->theme = $theme;
    }

    public function accentColor(): BrandColor
    {
        return $this->accentColor;
    }

    public function buttonShape(): ButtonShape
    {
        return $this->buttonShape;
    }

    public function theme(): PageTheme
    {
        return $this->theme;
    }
}
