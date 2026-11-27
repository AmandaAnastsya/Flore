@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $paid = $order->payment_status === 'confirmed';
    $date = $order->delivery_date->locale('id')->translatedFormat('l, d F Y');
@endphp
<x-mail::message>
# {{ $headline }}

Halo, {{ $user->name }}!

@foreach($lines as $line)
{{ $line }}

@endforeach
@if($showInvoice)
## Invoice INV/{{ $order->order_number }} — {{ $paid ? 'LUNAS' : 'BELUM DIBAYAR' }}

<x-mail::table>
| Item | Jumlah | Subtotal |
|:-----|:------:|---------:|
@foreach($order->items as $item)
| {{ $item->name }}{{ $item->isStem() ? ' (per tangkai)' : '' }} | {{ $item->quantity }} | {{ $rp($item->subtotal) }} |
@endforeach
| Subtotal | | {{ $rp($order->subtotal) }} |
| Ongkos kirim | | {{ (float) $order->delivery_fee > 0 ? $rp($order->delivery_fee) : 'Gratis' }} |
@if((float) $order->discount > 0)
| Diskon | | − {{ $rp($order->discount) }} |
@endif
| **Total** | | **{{ $rp($order->total_amount) }}** |
</x-mail::table>
@endif

@if($event === 'placed' || $event === 'payment_rejected')
<x-mail::panel>
**Transfer ke rekening {{ $bank['bank'] }}**<br>
No. rekening: **{{ $bank['account_number'] }}**<br>
Atas nama: **{{ $bank['account_name'] }}**<br>
Jumlah: **{{ $rp($order->total_amount) }}**
</x-mail::panel>
@endif

**Pengiriman**<br>
{{ $order->recipient_name }} · {{ $order->recipient_phone }}<br>
{{ $order->delivery_address }}, {{ $order->delivery_city }}<br>
{{ $date }} · {{ $slots[$order->delivery_slot] ?? '' }}

@if(in_array($event, ['placed', 'payment_rejected'], true))
<x-mail::button :url="$orderUrl" color="primary">
Unggah Bukti Transfer
</x-mail::button>
@elseif($event === 'delivered')
<x-mail::button :url="$orderUrl" color="primary">
Tulis Testimonial
</x-mail::button>
@else
<x-mail::button :url="$orderUrl" color="primary">
Lacak Pesanan
</x-mail::button>
@endif

@if($showInvoice)
Invoice lengkap bisa dilihat dan disimpan sebagai PDF di [halaman invoice]({{ $invoiceUrl }}).
@endif

Salam hangat,<br>
{{ config('flore.store.name', 'Floré') }} Studio

<x-slot:subcopy>
Email ini dikirim otomatis untuk pesanan {{ $order->order_number }}.
@unless(\App\Notifications\OrderUpdate::isMandatory($event))
Kamu bisa mematikan email update status pesanan di [Pengaturan Akun]({{ route('account.settings') }}).
@endunless
@if(config('flore.store.phone')) Butuh bantuan? WhatsApp {{ config('flore.store.phone') }}. @endif
</x-slot:subcopy>
</x-mail::message>
