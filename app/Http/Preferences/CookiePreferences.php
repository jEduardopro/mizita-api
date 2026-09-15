<?php

declare(strict_types=1);

namespace App\Http\Preferences;

use Illuminate\Http\Request;

final class CookiePreferences
{
    private ?Appearance $appearance = null;

    public function appearance(Request $request): Appearance
    {
        return $this->appearance ??= $this->readAppearance($request);
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
}
