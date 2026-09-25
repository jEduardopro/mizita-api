<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AddedToTeamNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private const CHANNELS = ['mail'];

    public function __construct(
        public readonly string $businessName,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return self::CHANNELS;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $business = ['business' => $this->businessName];

        return (new MailMessage)
            ->subject((string) __('team_invitations.added_to_team.subject', $business))
            ->greeting((string) __('team_invitations.greeting'))
            ->line((string) __('team_invitations.added_to_team.intro', $business))
            ->action((string) __('team_invitations.action'), route('login'))
            ->line((string) __('team_invitations.added_to_team.sign_in_notice'));
    }
}
