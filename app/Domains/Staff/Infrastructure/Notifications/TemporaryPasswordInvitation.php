<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use SensitiveParameter;

final class TemporaryPasswordInvitation extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    private const CHANNELS = ['mail'];

    public function __construct(
        public readonly string $businessName,
        public readonly string $email,
        #[SensitiveParameter]
        public readonly string $temporaryPassword,
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
            ->subject((string) __('team_invitations.temporary_password.subject', $business))
            ->greeting((string) __('team_invitations.greeting'))
            ->line((string) __('team_invitations.temporary_password.intro', $business))
            ->line((string) __('team_invitations.temporary_password.email', ['email' => $this->email]))
            ->line((string) __('team_invitations.temporary_password.password', ['password' => $this->temporaryPassword]))
            ->action((string) __('team_invitations.action'), route('login'))
            ->line((string) __('team_invitations.temporary_password.change_notice'));
    }
}
