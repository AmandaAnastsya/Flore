@extends('layouts.admin')
@section('title', 'Testimonial')

@section('content')
@php use App\Models\Testimonial; @endphp

@include('admin.reports._header', ['title' => 'Approve Testimonial', 'subtitle' => 'Hanya testimonial yang disetujui yang tampil di halaman depan toko.'])

@if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

<ul class="nav nav-pills mb-3 gap-2">
    @foreach(Testimonial::STATUS_LABELS as $key => $text)
        <li class="nav-item">
            <a href="{{ route('admin.testimonials.index', ['status' => $key]) }}"
               class="btn btn-sm {{ $status === $key ? 'btn-plum' : 'btn-outline-plum' }}">
                {{ $text }} <span class="badge bg-light text-dark ms-1">{{ $counts[$key] }}</span>
            </a>
        </li>
    @endforeach
</ul>

@forelse($testimonials as $t)
    <div class="panel mb-3">
        <div class="d-flex flex-wrap justify-content-between gap-2">
            <div>
                <div class="stars" aria-label="Rating {{ $t->rating }} dari 5">
                    {!! str_repeat('★', $t->rating) !!}<span class="off">{!! str_repeat('★', 5 - $t->rating) !!}</span>
                </div>
                <div class="fw-semibold">{{ $t->user?->name }}</div>
                <div class="small text-muted">
                    {{ $t->created_at->format('d M Y, H:i') }}
                    @if($t->order) · Pesanan <a href="{{ route('admin.orders.show', $t->order) }}">{{ $t->order->order_number }}</a> @endif
                </div>
            </div>
            <div class="d-flex gap-2 align-items-start">
                @unless($t->is_approved)
                    <form method="POST" action="{{ route('admin.testimonials.approve', $t) }}">
                        @csrf <button class="btn btn-sm btn-plum">Setujui</button>
                    </form>
                @endunless
                @if($t->status() !== 'rejected')
                    <form method="POST" action="{{ route('admin.testimonials.reject', $t) }}">
                        @csrf <button class="btn btn-sm btn-outline-secondary">{{ $t->is_approved ? 'Sembunyikan' : 'Tolak' }}</button>
                    </form>
                @endif
            </div>
        </div>
        <p class="mt-3 mb-0">“{{ $t->content }}”</p>
    </div>
@empty
    <div class="panel text-center text-muted py-5">Tidak ada testimonial dengan status "{{ Testimonial::STATUS_LABELS[$status] }}".</div>
@endforelse

<div class="mt-3">{{ $testimonials->links() }}</div>
@endsection
