<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dikirim ke alamat email LAMA saat email login akun diganti. */
class EmailChanged extends Notification
{
    use Queueable;

    public function __construct(public string $name, public string $newEmail)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // sembunyikan sebagian alamat baru: cl****@gmail.com
        [$local, $domain] = array_pad(explode('@', $this->newEmail, 2), 2, '');
        $masked = mb_substr($local, 0, 2) . str_repeat('*', max(1, mb_strlen($local) - 2)) . '@' . $domain;

        return (new MailMessage)
            ->subject('Email akun Floré kamu telah diganti')
            ->greeting("Halo, {$this->name}!")
            ->line("Email login akun Floré kamu baru saja diganti menjadi {$masked}.")
            ->line('Jika bukan kamu yang mengganti, segera hubungi kami agar akunmu bisa diamankan.')
            ->salutation("Salam hangat,\n" . config('flore.store.name', 'Floré') . ' Studio');
    }
}
