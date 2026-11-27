<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dua arah: ke email toko (pesan baru masuk) dan ke pengirim (tanda terima).
 * $forStore = true → email untuk admin/toko.
 */
class ContactReceived extends Notification
{
    use Queueable;

    public function __construct(public ContactMessage $contact, public bool $forStore = false)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $c = $this->contact;
        $topic = ContactMessage::TOPICS[$c->topic] ?? $c->topic;
        $store = config('flore.store.name', 'Floré');

        if ($this->forStore) {
            return (new MailMessage)
                ->subject("Pesan baru dari {$c->name}: {$topic}")
                ->replyTo($c->email, $c->name)
                ->greeting('Pesan baru dari halaman Contact Us')
                ->line("**{$c->name}** ({$c->email}" . ($c->phone ? " · {$c->phone}" : '') . ')')
                ->line("Topik: **{$topic}**" . ($c->order_number ? " · Pesanan {$c->order_number}" : ''))
                ->line($c->message)
                ->action('Buka Kotak Masuk', route('admin.messages.index'))
                ->salutation('Balas langsung email ini untuk menjawab pengirim.');
        }

        return (new MailMessage)
            ->subject("Pesanmu sudah kami terima · {$store}")
            ->greeting("Halo, {$c->name}!")
            ->line("Terima kasih sudah menghubungi {$store}. Pesanmu tentang **{$topic}** sudah kami terima dan akan dibalas secepatnya pada jam operasional.")
            ->line('Isi pesanmu:')
            ->line('“' . $c->message . '”')
            ->salutation("Salam hangat,\n{$store} Studio");
    }
}
