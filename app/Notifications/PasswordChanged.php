<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Pemberitahuan keamanan: password akun baru saja diganti. */
class PasswordChanged extends Notification
{
    use Queueable;

    public function __construct(public ?string $ip = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = $notifiable->isAdmin() ? route('admin.login') : route('password.request');

        return (new MailMessage)
            ->subject('Password akun Floré kamu telah diganti')
            ->greeting("Halo, {$notifiable->name}!")
            ->line('Password akun Floré kamu baru saja diganti pada ' . now()->locale('id')->translatedFormat('l, d F Y H:i') . ' WIB'
                . ($this->ip ? " dari alamat IP {$this->ip}." : '.'))
            ->line('Jika ini kamu, tidak ada yang perlu dilakukan.')
            ->line('Jika bukan kamu, segera atur ulang password lewat tautan di bawah dan hubungi kami.')
            ->action('Atur Ulang Password', $resetUrl)
            ->salutation("Salam hangat,\n" . config('flore.store.name', 'Floré') . ' Studio');
    }
}
