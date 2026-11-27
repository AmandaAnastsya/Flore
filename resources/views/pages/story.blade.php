@extends('layouts.app')
@section('title', 'Our Story')

@section('content')
<div class="container py-4 info-page">
    @include('pages._hero', ['title' => 'Our Story & Philosophy', 'subtitle' => 'Mewujudkan perasaan melalui seni merangkai bunga artisanal.'])

    {{-- Profil brand --}}
    <section class="panel story-profile mb-5">
        <div class="story-photo">
            @if($photo)
                <img src="{{ $photo->image_url }}" alt="Rangkaian {{ $photo->name }} dari studio {{ config('flore.store.name', 'Floré') }}">
            @else
                <span>{{ config('flore.store.name', 'Floré') }} Workshop</span>
            @endif
        </div>
        <div>
            <h2 class="section-title h5">Profil Brand {{ config('flore.store.name', 'Floré') }} Studio</h2>
            <p>{{ config('flore.store.name', 'Floré') }} Studio percaya bahwa setiap tangkai bunga memiliki bahasa emosinya sendiri.
               Kami memadukan estetika florist Eropa klasik dengan sentuhan modern yang minimalis untuk menciptakan rangkaian yang puitis dan bermakna.</p>
            <p class="mb-0">Setiap pesanan dirangkai dengan tangan di studio kami, diperiksa kualitasnya sebelum dikirim, dan diantar langsung oleh kurir kami
               agar bunga tiba dalam kondisi terbaik.</p>
        </div>
    </section>

    {{-- Filosofi --}}
    <h2 class="section-title h5 mb-3">Filosofi Desain Bunga Kami</h2>
    <div class="row g-3 mb-5">
        @foreach([
            ['Artisanal Composition', 'Rangkaian asimetris alami yang menonjolkan bentuk asli setiap bunga — tidak ada dua bouquet yang benar-benar sama.', 'plum'],
            ['Eco-Conscious Sourcing', 'Bunga dipilih dari petani lokal terpercaya dan dipesan secukupnya, supaya segar dan tidak banyak terbuang.', 'blush'],
            ['Emotional Storytelling', 'Setiap warna dan tekstur dirancang sesuai pesan yang ingin kamu sampaikan, dibantu Smart Recommendation Wizard.', 'blush'],
        ] as [$t, $d, $tone])
            <div class="col-md-4">
                <div class="philosophy-card {{ $tone }} h-100">
                    <h3>{{ $t }}</h3>
                    <p class="mb-0">{{ $d }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Tim florist: dari data florist aktif di admin --}}
    @if($florists->isNotEmpty())
        <section class="panel mb-5">
            <h2 class="section-title h5 mb-3">Meet Our Florists</h2>
            <div class="row g-3">
                @foreach($florists as $f)
                    <div class="col-sm-6 col-lg-4">
                        <div class="florist-card">
                            <div class="florist-avatar" aria-hidden="true">{{ collect(explode(' ', $f->name))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('') }}</div>
                            <div>
                                <div class="fw-semibold">{{ $f->name }}</div>
                                <div class="small text-mauve">{{ $f->specialty ?? 'Florist' }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Kutipan pelanggan: testimonial yang sudah disetujui admin --}}
    @if($quote)
        <blockquote class="story-quote mb-5">
            <p>“{{ $quote->content }}”</p>
            <footer>— {{ $quote->user?->name ?? 'Pelanggan Floré' }}</footer>
        </blockquote>
    @endif

    <div class="text-center">
        <a href="{{ route('catalog.index') }}" class="btn btn-plum me-2">Lihat Koleksi</a>
        <a href="{{ route('pages.care') }}" class="btn btn-outline-plum">Panduan Merawat Bunga</a>
    </div>
</div>
@endsection
