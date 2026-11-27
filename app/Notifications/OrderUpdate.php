<?php

namespace App\Notifications;

use App\Models\Order;
use App\Services\DeliverySchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email invoice & notifikasi status pesanan ke customer.
 * Dikirim oleh App\Observers\OrderObserver setelah perubahan tersimpan (after commit).
 */
class OrderUpdate extends Notification
{
    use Queueable;

    /**
     * kunci => [subjek, judul, wajib?]  — "wajib" = transaksional, selalu dikirim
     * walaupun customer mematikan email update status pesanan.
     */
    public const EVENTS = [
        'placed' => ['Invoice {no} — menunggu pembayaran', 'Terima kasih, pesananmu sudah kami terima!', true],
        'proof_uploaded' => ['Bukti pembayaran {no} sedang diverifikasi', 'Bukti transfer sudah kami terima', false],
        'paid' => ['Pembayaran {no} dikonfirmasi — Invoice LUNAS', 'Pembayaran berhasil dikonfirmasi', true],
        'payment_rejected' => ['Bukti pembayaran {no} perlu diunggah ulang', 'Bukti pembayaran belum bisa kami terima', true],
        'preparing' => ['Pesanan {no} mulai dirangkai', 'Florist kami mulai merangkai pesananmu', false],
        'out_for_delivery' => ['Pesanan {no} sedang dalam perjalanan', 'Bunga untukmu sedang diantar!', false],
        'delivered' => ['Pesanan {no} sudah diterima', 'Pesananmu sudah sampai', false],
        'cancelled' => ['Pesanan {no} dibatalkan', 'Pesananmu dibatalkan', true],
    ];

    public function __construct(public Order $order, public string $event)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public static function isMandatory(string $event): bool
    {
        return self::EVENTS[$event][2] ?? true;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $o = $this->order->loadMissing('items', 'courier');
        [$subject, $headline] = self::EVENTS[$this->event];

        return (new MailMessage)
            ->subject(str_replace('{no}', $o->order_number, $subject) . ' · ' . config('flore.store.name', 'Floré'))
            ->markdown('mail.order-update', [
                'order' => $o,
                'user' => $notifiable,
                'event' => $this->event,
                'headline' => $headline,
                'lines' => $this->lines($o),
                'slots' => DeliverySchedule::labels(),
                'bank' => config('flore.payment'),
                'orderUrl' => route('orders.show', $o->order_number),
                'invoiceUrl' => route('orders.invoice', $o->order_number),
                'showInvoice' => in_array($this->event, ['placed', 'paid'], true),
            ]);
    }

    /** Paragraf pembuka per kejadian. */
    private function lines(Order $o): array
    {
        $date = $o->delivery_date->locale('id')->translatedFormat('l, d F Y');
        $rp = 'Rp ' . number_format((float) $o->total_amount, 0, ',', '.');

        return match ($this->event) {
            'placed' => [
                "Pesanan **{$o->order_number}** untuk **{$o->recipient_name}** dijadwalkan dikirim **{$date}**.",
                "Silakan transfer **{$rp}** ke rekening di bawah, lalu unggah bukti transfer agar pesanan bisa segera kami proses.",
            ],
            'proof_uploaded' => ['Admin kami sedang memeriksa bukti transfermu. Kamu akan menerima email konfirmasi setelah pembayaran diverifikasi.'],
            'paid' => [
                'Pembayaran pesanan **' . $o->order_number . '** sebesar **' . $rp . '** telah kami terima'
                    . ($o->paid_at ? ' pada ' . $o->paid_at->locale('id')->translatedFormat('d F Y, H:i') : '') . '.',
                'Invoice di bawah ini sudah berstatus **LUNAS** dan bisa kamu simpan sebagai kuitansi.',
            ],
            'payment_rejected' => [
                'Bukti transfer untuk pesanan **' . $o->order_number . '** belum sesuai (misalnya nominal berbeda atau gambar tidak terbaca).',
                'Silakan unggah ulang bukti transfer yang benar dari halaman pesanan.',
            ],
            'preparing' => [
                'Pembayaran sudah beres — bunga untuk **' . $o->recipient_name . '** sedang dipotong dan dirangkai di studio.',
                "Pesanan dijadwalkan dikirim {$date}.",
            ],
            'out_for_delivery' => array_values(array_filter([
                'Pesanan **' . $o->order_number . '** sudah berangkat dari studio menuju **' . $o->delivery_city . '**.',
                $o->courier ? "Kurir: **{$o->courier->name}** ({$o->courier->vehicleLabel()})." : null,
                $o->eta_at ? 'Perkiraan tiba sekitar pukul **' . $o->eta_at->format('H:i') . '**.' : null,
            ])),
            'delivered' => array_values(array_filter([
                'Pesanan **' . $o->order_number . '** sudah diterima'
                    . ($o->received_by ? ' oleh **' . $o->received_by . '**' : '')
                    . ($o->delivered_at ? ' pada ' . $o->delivered_at->locale('id')->translatedFormat('d F Y, H:i') : '') . '.',
                'Semoga bunganya membuat momenmu makin berkesan. Ceritakan pengalamanmu lewat testimonial di halaman pesanan ya!',
            ])),
            'cancelled' => array_values(array_filter([
                'Pesanan **' . $o->order_number . '** telah dibatalkan' . ($o->cancelled_at ? ' pada ' . $o->cancelled_at->locale('id')->translatedFormat('d F Y, H:i') : '') . '.',
                $o->paid_at ? 'Karena pembayaran sudah kami terima, tim kami akan menghubungimu untuk proses pengembalian dana.' : null,
                'Jika ini tidak sesuai, balas email ini atau hubungi customer service kami.',
            ])),
            default => [],
        };
    }
}
