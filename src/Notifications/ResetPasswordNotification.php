<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Notifications;

use BunnySidecar\BunnySidecar\BunnySidecar;
use BunnySidecar\BunnySidecar\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use SensitiveParameter;

/**
 * Password reset mail pointing at the panel's own reset page.
 */
class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(#[SensitiveParameter] public string $token) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $expire = config()->integer('bunny-sidecar.password_reset.expire', 60);

        return (new MailMessage)
            ->subject(__('Reset Password Notification'))
            ->line(__('You are receiving this email because we received a password reset request for your :app account.', ['app' => app(BunnySidecar::class)->name()]))
            ->action(__('Reset Password'), $this->resetUrl($notifiable))
            ->line(__('This password reset link will expire in :count minutes.', ['count' => $expire]))
            ->line(__('If you did not request a password reset, no further action is required.'));
    }

    public function resetUrl(User $notifiable): string
    {
        return route('bunny-sidecar.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->email,
        ]);
    }
}
