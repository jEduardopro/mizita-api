<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Notifications;

use App\Domains\Staff\Contracts\TeamInvitationMailer;
use App\Domains\Staff\ValueObjects\TeamInvitation;
use App\Models\User;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Notifications\Notification;

final class NotificationTeamInvitationMailer implements TeamInvitationMailer
{
    public function send(TeamInvitation $invitation): void
    {
        $account = User::query()->where('uuid', $invitation->accountId)->first();

        if ($account === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$invitation->accountId]);
        }

        $account->notify($this->notificationFor($invitation, $account)->locale($this->localeFor($account)));
    }

    private function notificationFor(TeamInvitation $invitation, User $account): Notification
    {
        if ($invitation->temporaryPassword === null) {
            return new AddedToTeamNotification($invitation->businessName);
        }

        return new TemporaryPasswordInvitation(
            businessName: $invitation->businessName,
            email: (string) $account->email,
            temporaryPassword: $invitation->temporaryPassword,
        );
    }

    private function localeFor(User $account): string
    {
        $preferred = $account instanceof HasLocalePreference ? $account->preferredLocale() : null;

        return is_string($preferred) && $preferred !== '' ? $preferred : app()->getLocale();
    }
}
