<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use SensitiveParameter;

final class QueuedResetPassword extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    private readonly string $resetLink;

    public function __construct(#[SensitiveParameter] string $token, CanResetPassword $notifiable)
    {
        parent::__construct($token);

        $this->resetLink = parent::resetUrl($notifiable);

        $this->afterCommit();
    }

    protected function resetUrl($notifiable): string
    {
        return $this->resetLink;
    }
}
