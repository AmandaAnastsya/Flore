@extends('layouts.app')
@section('title', 'FAQ & Shipping Policy')

@section('content')
@php
    $waNumber = $phone ? preg_replace('/^0/', '62', preg_replace('/\D/', '', $phone)) : null;
    $slotText = implode(', ', $slots);
@endphp
<div class="container py-4 info-page">
    @include('pages._hero', ['title' => 'FAQ & Shipping Policy', 'subtitle' => 'Informasi jangkauan pengiriman, pembayaran, toleransi keterlambatan, dan garansi kesegaran.'])

    {{-- 1. Pengiriman --}}
    <h2 class="section-title h5 mb-3">1. Jangkauan Area & Metode Pengiriman</h2>
    <div class="panel mb-5">
        <ul class="faq-points mb-0">
            <li><strong>Kurir studio (Jabodetabek):</strong> diantar langsung oleh kurir {{ config('flore.store.name', 'Floré') }} dari studio di {{ config('flore.studio.address') }}. Ongkos kirim saat ini <strong>gratis</strong>.</li>
            <li><strong>Slot waktu kedatangan:</strong> {{ $slotText }}.</li>
            <li><strong>Same-day delivery:</strong> untuk bouquet berlabel <em>Same Day</em>, pesan minimal {{ $leadHours }} jam sebelum slot dimulai.</li>
            <li><strong>Pesan lebih awal:</strong> pengiriman bisa dijadwalkan hingga {{ $maxDays }} hari ke depan.</li>
            <li><strong>Lacak pesanan:</strong> status, nama kurir, dan perkiraan jam tiba terlihat di halaman <a href="{{ route('orders.index') }}">Pesanan Saya</a> dan dikirim lewat email.</li>
        </ul>
    </div>

    {{-- 2. Pembayaran & pembatalan --}}
    <h2 class="section-title h5 mb-3">2. Pembayaran & Pembatalan</h2>
    <div class="faq-list mb-5">
        <details class="faq-item">
            <summary>Bagaimana cara membayar?</summary>
            <p>Transfer ke rekening <strong>{{ $bank['bank'] }} {{ $bank['account_number'] }}</strong> a.n. <strong>{{ $bank['account_name'] }}</strong>,
               lalu unggah bukti transfer (JPG, PNG, WEBP, atau PDF maks. 3 MB) di halaman pesanan. Invoice dikirim ke email setelah pesanan dibuat.</p>
        </details>
        <details class="faq-item">
            <summary>Berapa lama pembayaran diverifikasi?</summary>
            <p>Admin memeriksa bukti transfer pada jam operasional. Kamu akan menerima email “Invoice LUNAS” begitu pembayaran dikonfirmasi, lalu bouquet mulai dirangkai.</p>
        </details>
        <details class="faq-item">
            <summary>Bisakah saya membatalkan pesanan?</summary>
            <p>Bisa langsung dari halaman pesanan selama bukti transfer belum diunggah. Setelah itu, hubungi customer service kami agar pembatalan dan pengembalian dana diproses admin.</p>
        </details>
    </div>

    {{-- 3. Keterlambatan & garansi (kebijakan dari desain toko) --}}
    <h2 class="section-title h5 mb-3">3. Kebijakan Keterlambatan & Garansi Kerusakan</h2>
    <div class="faq-list mb-5">
        <details class="faq-item" open>
            <summary>Berapa toleransi keterlambatan pengiriman?</summary>
            <p>Kami memberikan toleransi keterlambatan maksimal 45 menit dari slot waktu yang disepakati akibat cuaca buruk atau kemacetan ekstrem.
               Jika pesanan terlambat lebih dari 45 menit, pelanggan berhak menerima voucher diskon 20% untuk pembelian berikutnya.</p>
        </details>
        <details class="faq-item">
            <summary>Bagaimana jika bunga rusak atau layu saat diterima?</summary>
            <p>Kami memberikan garansi 100% replacement (ganti bunga baru) jika rangkaian diterima dalam kondisi layu berat, batang patah, atau rusak saat pengiriman oleh kurir kami.</p>
        </details>
        <details class="faq-item">
            <summary>Apa syarat dan prosedur pengajuan klaim garansi?</summary>
            <ol class="mb-0">
                <li>Kirimkan foto & video unboxing maksimal 3 jam setelah paket diterima.</li>
                <li>Hubungi customer service dengan menyertakan nomor pesanan (FLR-…).</li>
                <li>Rangkaian pengganti dikirim di hari yang sama, atau dana dikembalikan 100%.</li>
            </ol>
        </details>
    </div>

    {{-- 4. Fitur akun --}}
    <h2 class="section-title h5 mb-3">4. Akun, Smart Wizard & Pengingat</h2>
    <div class="faq-list mb-5">
        <details class="faq-item">
            <summary>Bingung memilih bouquet?</summary>
            <p>Gunakan <a href="{{ route('wizard.index') }}">Smart Recommendation Wizard</a> — jawab 5 pertanyaan singkat dan kami tampilkan bouquet yang paling cocok beserta alasannya.</p>
        </details>
        <details class="faq-item">
            <summary>Bagaimana cara mendapatkan pengingat ulang tahun / anniversary?</summary>
            <p>Simpan tanggal penting di halaman Momen Spesial & Pengingat. Kami mengirim email pengingat beberapa hari sebelumnya sesuai pilihanmu.</p>
        </details>
    </div>

    {{-- CTA kontak --}}
    <section class="faq-contact">
        <div>
            <h2 class="h6 mb-1">Masih memiliki pertanyaan lain?</h2>
            <p class="small mb-0">Tim customer service {{ config('flore.store.name', 'Floré') }} siap membantu setiap hari.</p>
        </div>
        <a href="{{ route('contact.show') }}" class="btn btn-outline-plum">Kirim Pesan</a>
        @if($waNumber)
            <a href="https://wa.me/{{ $waNumber }}" target="_blank" rel="noopener" class="btn btn-plum">Hubungi WhatsApp</a>
        @elseif($email)
            <a href="mailto:{{ $email }}" class="btn btn-plum">Kirim Email</a>
        @else
            <a href="{{ route('orders.index') }}" class="btn btn-plum">Lihat Pesanan Saya</a>
        @endif
    </section>
</div>
@endsection
