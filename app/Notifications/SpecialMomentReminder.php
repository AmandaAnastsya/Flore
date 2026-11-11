<?php

namespace App\Notifications;

use App\Models\Reminder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email Smart Reminder. Dikirim langsung (tidak di-queue) oleh perintah reminders:send
 * supaya hasil kirim — berhasil atau gagal — bisa dicatat di tabel reminders.
 */
class SpecialMomentReminder extends Notification
{
    use Queueable;

    public function __construct(public Reminder $reminder, public ?string $catalogOccasion = null)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->reminder;
        $date = $r->occasion_date->locale('id')->translatedFormat('l, d F Y');
        $days = (int) today()->diffInDays($r->occasion_date, false);
        $when = match (true) {
            $days <= 0 => 'hari ini',
            $days === 1 => 'besok',
            default => "{$days} hari lagi",
        };

        $catalogUrl = route('catalog.index', array_filter(['occasion' => $this->catalogOccasion]));

        return (new MailMessage)
            ->subject("Pengingat Floré: {$r->title} {$when} 🌸")
            ->greeting("Halo, {$notifiable->name}!")
            ->line("Kami ingin mengingatkan momen spesialmu: **{$r->title}** jatuh pada **{$date}** ({$when}).")
            ->line('Siapkan rangkaian bunga yang berkesan dari sekarang supaya bisa dikirim tepat waktu.')
            ->action('Pilih Bouquet Sekarang', $catalogUrl)
            ->line('Bingung memilih? Coba Smart Recommendation Wizard: ' . route('wizard.index'))
            ->line('Pengingat ini bisa kamu ubah atau matikan kapan saja di halaman Profil & Momen Spesial: ' . route('profile.edit'))
            ->salutation("Salam hangat,\nFloré Studio");
    }
}
