<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

use App\Shared\Contracts\BusinessSelection;
use Illuminate\Http\Request;

final readonly class SessionBusinessSelection implements BusinessSelection
{
    private const SESSION_KEY = 'selected_business';

    private const ACCOUNT_KEY = 'account_id';

    private const BUSINESS_KEY = 'business_id';

    public function __construct(
        private Request $request,
    ) {}

    public function selectedBusinessIdFor(string $accountId): ?string
    {
        $selection = $this->storedSelectionOf($accountId);

        if ($selection === null) {
            return null;
        }

        $businessId = $selection[self::BUSINESS_KEY] ?? null;

        return is_string($businessId) ? $businessId : null;
    }

    public function rememberFor(string $accountId, string $businessId): void
    {
        if (! $this->request->hasSession()) {
            return;
        }

        $this->request->session()->put(self::SESSION_KEY, [
            self::ACCOUNT_KEY => $accountId,
            self::BUSINESS_KEY => $businessId,
        ]);
    }

    public function forgetFor(string $accountId): void
    {
        if ($this->storedSelectionOf($accountId) === null) {
            return;
        }

        $this->request->session()->forget(self::SESSION_KEY);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function storedSelectionOf(string $accountId): ?array
    {
        if (! $this->request->hasSession()) {
            return null;
        }

        $selection = $this->request->session()->get(self::SESSION_KEY);

        if (! is_array($selection) || ($selection[self::ACCOUNT_KEY] ?? null) !== $accountId) {
            return null;
        }

        return $selection;
    }
}
