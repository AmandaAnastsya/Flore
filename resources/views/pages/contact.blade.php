@extends('layouts.app')
@section('title', 'Hubungi Kami')

@section('content')
@php
    use App\Models\ContactMessage;
    $waNumber = $phone ? preg_replace('/^0/', '62', preg_replace('/\D/', '', $phone)) : null;
@endphp
<div class="container py-4 info-page">
    @include('pages._hero', ['title' => 'Contact Us', 'subtitle' => 'Ada pertanyaan, pesanan khusus, atau keluhan? Kirim pesan — kami balas ke emailmu.'])

    @include('catalog._flash')

    <div class="row g-4">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('contact.store') }}" class="panel" novalidate>
                @csrf
                {{-- jebakan bot: disembunyikan dari manusia --}}
                <div class="visually-hidden" aria-hidden="true">
                    <label for="website">Website</label>
                    <input id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Nama</label>
                        <input id="name" name="name" maxlength="100" required autocomplete="name" value="{{ old('name', $user?->name) }}" class="form-control @error('name') is-invalid @enderror">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input id="email" type="email" name="email" maxlength="150" required autocomplete="email" value="{{ old('email', $user?->email) }}" class="form-control @error('email') is-invalid @enderror">
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label">No. HP / WhatsApp <span class="text-muted small">(opsional)</span></label>
                        <input id="phone" name="phone" inputmode="tel" autocomplete="tel" value="{{ old('phone', $user?->phone) }}" placeholder="081234567890" class="form-control @error('phone') is-invalid @enderror">
                        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="topic" class="form-label">Topik</label>
                        <select id="topic" name="topic" class="form-select @error('topic') is-invalid @enderror">
                            @foreach(ContactMessage::TOPICS as $k => $label)
                                <option value="{{ $k }}" @selected(old('topic', $orderNumber ? 'order' : null) === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('topic') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label for="order_number" class="form-label">Nomor pesanan <span class="text-muted small">(opsional)</span></label>
                        <input id="order_number" name="order_number" maxlength="30" value="{{ old('order_number', $orderNumber) }}" placeholder="FLR-261124-AB12C" class="form-control @error('order_number') is-invalid @enderror">
                        @error('order_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label for="message" class="form-label">Pesan</label>
                        <textarea id="message" name="message" rows="5" maxlength="2000" required class="form-control @error('message') is-invalid @enderror">{{ old('message') }}</textarea>
                        @error('message') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <button class="btn btn-plum mt-3">Kirim Pesan</button>
            </form>
        </div>
        <div class="col-lg-5">
            <div class="panel mb-3">
                <h2 class="panel-title">Kontak Langsung</h2>
                <ul class="faq-points mb-0">
                    <li><strong>Studio:</strong> {{ config('flore.studio.address') }}</li>
                    @if($phone) <li><strong>WhatsApp:</strong> <a href="https://wa.me/{{ $waNumber }}" target="_blank" rel="noopener">{{ $phone }}</a></li> @endif
                    @if($email) <li><strong>Email:</strong> <a href="mailto:{{ $email }}">{{ $email }}</a></li> @endif
                    <li><strong>Pengiriman:</strong> pagi, siang, dan sore setiap hari</li>
                </ul>
            </div>
            <div class="panel">
                <h2 class="panel-title">Sebelum bertanya</h2>
                <p class="small mb-2">Jawaban seputar pengiriman, pembayaran, pembatalan, dan garansi ada di <a href="{{ route('pages.faq') }}">FAQ & Shipping Policy</a>.</p>
                @auth <p class="small mb-0">Status pesananmu bisa dicek di <a href="{{ route('orders.index') }}">Pesanan Saya</a>.</p> @endauth
            </div>
        </div>
    </div>
</div>
@endsection
