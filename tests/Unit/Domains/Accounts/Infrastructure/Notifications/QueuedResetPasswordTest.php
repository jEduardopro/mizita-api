<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Notifications\QueuedResetPassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

uses(TestCase::class);

const QUEUED_RESET_TOKEN = 'c3f1a9d27e4b8f0612a5d4e9b7c0f3a8';

function resetPasswordRecipient(): User
{
    return (new User)->forceFill([
        'uuid' => '01930000-0000-7000-8000-0000000000d1',
        'email' => 'ada+reset@example.com',
    ]);
}

it('is queued rather than sent inline', function () {
    expect(new QueuedResetPassword(QUEUED_RESET_TOKEN, resetPasswordRecipient()))->toBeInstanceOf(ShouldQueue::class);
});

it('is encrypted on the queue, because the link is a bearer credential', function () {
    expect(new QueuedResetPassword(QUEUED_RESET_TOKEN, resetPasswordRecipient()))->toBeInstanceOf(ShouldBeEncrypted::class);
});

it('waits for the surrounding transaction to commit before it is queued', function () {
    expect((new QueuedResetPassword(QUEUED_RESET_TOKEN, resetPasswordRecipient()))->afterCommit)->toBeTrue();
});

it('links to exactly the page the framework reset notification would', function () {
    $recipient = resetPasswordRecipient();

    $queued = (new QueuedResetPassword(QUEUED_RESET_TOKEN, $recipient))->toMail($recipient)->actionUrl;

    expect($queued)->toBe((new ResetPassword(QUEUED_RESET_TOKEN))->toMail($recipient)->actionUrl)
        ->and($queued)->toContain(QUEUED_RESET_TOKEN)
        ->and($queued)->toContain(urlencode('ada+reset@example.com'));
});

it('keeps the link it computed when dispatched, whatever host the worker later resolves', function () {
    $recipient = resetPasswordRecipient();
    $notification = new QueuedResetPassword(QUEUED_RESET_TOKEN, $recipient);
    $dispatchedLink = (new ResetPassword(QUEUED_RESET_TOKEN))->toMail($recipient)->actionUrl;

    URL::forceRootUrl('https://worker.internal');

    expect($notification->toMail($recipient)->actionUrl)->toBe($dispatchedLink)
        ->and($notification->toMail($recipient)->actionUrl)->not->toContain('worker.internal');
});

it('carries the same link through serialization onto the queue', function () {
    $recipient = resetPasswordRecipient();
    $notification = new QueuedResetPassword(QUEUED_RESET_TOKEN, $recipient);

    $restored = unserialize(serialize($notification));

    expect($restored->toMail($recipient)->actionUrl)->toBe($notification->toMail($recipient)->actionUrl);
});
