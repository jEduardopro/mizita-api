<?php

declare(strict_types=1);

namespace App\Http\Preferences;

use Illuminate\Http\Request;

final class CookiePreferences
{
    private ?Appearance $appearance = null;

    private ?bool $sidebarOpen = null;

    public function appearance(Request $request): Appearance
    {
        return $this->appearance ??= $this->readAppearance($request);
    }

    public function sidebarOpen(Request $request): bool
    {
        return $this->sidebarOpen ??= $this->readSidebarOpen($request);
    }

    private function readAppearance(Request $request): Appearance
    {
        $stored = $request->cookie((string) config('preferences.appearance.cookie'));

        if (! is_string($stored) || $stored === '') {
            return $this->defaultAppearance();
        }

        return Appearance::tryFrom($stored) ?? $this->defaultAppearance();
    }

    private function defaultAppearance(): Appearance
    {
        return Appearance::from((string) config('preferences.appearance.default'));
    }

    private function readSidebarOpen(Request $request): bool
    {
        $stored = $request->cookie((string) config('preferences.sidebar.cookie'));

        if (! is_string($stored) || $stored === '') {
            return $this->defaultSidebarOpen();
        }

        return filter_var($stored, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ?? $this->defaultSidebarOpen();
    }

    private function defaultSidebarOpen(): bool
    {
        return (bool) config('preferences.sidebar.default');
    }
}
