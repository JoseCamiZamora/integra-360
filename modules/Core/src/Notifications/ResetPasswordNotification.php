<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Core\Models\User;

/**
 * Password recovery link, for users who have an e-mail. Queued (slow task).
 */
final class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        #[\SensitiveParameter]
        public readonly string $token,
    ) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->email]);

        return (new MailMessage)
            ->subject(__('core::auth.mail.subject'))
            ->greeting(__('core::auth.mail.greeting', ['name' => $notifiable->name]))
            ->line(__('core::auth.mail.line'))
            ->action(__('core::auth.mail.action'), $url)
            ->line(__('core::auth.mail.expire', ['count' => config('auth.passwords.users.expire')]))
            ->line(__('core::auth.mail.ignore'));
    }
}
