@extends('layouts.app')

@section('content')
<div class="container catalog">

    {{-- Hero --}}
    <section class="hero mb-4 d-md-flex justify-content-between align-items-center gap-4">
        <div class="mb-3 mb-md-0">
            <h1 class="mb-1">Craft Your Moment With Floré</h1>
            <p class="text-muted mb-3">Soft Elegance Recommendation Wizard — dikurasi berdasarkan gaya, perasaan & budget.</p>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <button type="button" class="btn btn-plum px-4 text-nowrap" data-bs-toggle="modal" data-bs-target="#wizardModal">
                    Open Smart Wizard ✿
                </button>
                @guest
                    <a href="{{ route('register') }}" class="text-mauve small">Buat akun</a>
                @endguest
            </div>
        </div>
        <a href="{{ route('catalog.index') }}" class="hero-feature text-decoration-none d-block flex-shrink-0">
            Pastel Bloom Collection
            <div class="text-muted mt-1" style="text-transform:none;letter-spacing:0">Handcrafted soft aesthetic →</div>
        </a>
    </section>

    {{-- Koleksi --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="section-title h5 mb-0">The Elegance Collection</h2>
        <a href="{{ route('catalog.index') }}" class="small">Lihat semua koleksi →</a>
    </div>

    @if($featured->isEmpty())
        <p class="text-muted">Koleksi segera hadir.</p>
    @else
        <div class="row g-4 mb-5">
            @foreach($featured as $bouquet)
                <div class="col-12 col-sm-6 col-lg-4">
                    @include('catalog._card', ['bouquet' => $bouquet])
                </div>
            @endforeach
        </div>
    @endif

    {{-- Band Smart Wizard --}}
    <section class="wizard-band d-lg-flex justify-content-between align-items-center gap-4">
        <div class="mb-3 mb-lg-0">
            <h2 class="mb-1">Floré Smart Recommendation Wizard</h2>
            <p class="mb-0" style="color: rgba(255,255,255,.8)">
                Rekomendasi bunga personal untuk momen spesial dan estetikamu — cukup 5 pertanyaan singkat.
            </p>
        </div>
        <button type="button" class="btn btn-soft px-4 flex-shrink-0" data-bs-toggle="modal" data-bs-target="#wizardModal">
            Generate Recommendation
        </button>
    </section>

    {{-- Keunggulan --}}
    <div class="row g-3 mt-4">
        @foreach([
            ['Same Day Delivery', 'Pilih slot pagi, siang, atau sore untuk pengiriman.'],
            ['Kartu Ucapan Elegan', 'Tulis pesanmu, kami cetak dan selipkan di bouquet.'],
            ['Lacak Pesanan', 'Pantau status pesanan dari pembayaran sampai terkirim.'],
        ] as [$title, $text])
            <div class="col-md-4">
                <div class="panel h-100">
                    <div class="panel-title mb-1" style="font-size:.85rem">{{ $title }}</div>
                    <p class="small text-muted mb-0">{{ $text }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Testimonial yang disetujui admin --}}
    @if($testimonials->isNotEmpty())
        <h2 class="section-title h5 mt-5 mb-3">Kepercayaan Pelanggan</h2>
        <div class="row g-3">
            @foreach($testimonials as $t)
                <div class="col-md-4">
                    <figure class="panel h-100 mb-0">
                        <div class="stars mb-2" aria-label="Rating {{ $t->rating }} dari 5">
                            {!! str_repeat('★', $t->rating) !!}<span class="off">{!! str_repeat('★', 5 - $t->rating) !!}</span>
                        </div>
                        <blockquote class="small mb-2">“{{ $t->content }}”</blockquote>
                        <figcaption class="small fw-semibold text-plum">— {{ $t->user?->name ?? 'Pelanggan Floré' }}</figcaption>
                    </figure>
                </div>
            @endforeach
        </div>
    @endif
</div>

@include('wizard._modal')
@endsection
