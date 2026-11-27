@extends('layouts.admin')
@section('title', 'Pesan Masuk')

@section('content')
@php use App\Models\ContactMessage; @endphp
<div class="admin-head">
    <h1 class="h3 mb-1">Pesan Masuk (Contact Us)</h1>
    <p>Pertanyaan dan keluhan dari halaman Hubungi Kami. Balas lewat email atau WhatsApp, lalu tandai statusnya.</p>
</div>

@include('catalog._flash')

<nav class="nav report-tabs mb-3" aria-label="Filter status">
    <a href="{{ route('admin.messages.index') }}" class="nav-link {{ $status === null ? 'active' : '' }}">Semua <span class="tab-count">{{ $counts->sum() }}</span></a>
    @foreach(ContactMessage::STATUSES as $k => [$label])
        <a href="{{ route('admin.messages.index', ['status' => $k]) }}" class="nav-link {{ $status === $k ? 'active' : '' }}">
            {{ $label }} <span class="tab-count {{ $k === 'new' && ($counts[$k] ?? 0) ? 'attention' : '' }}">{{ $counts[$k] ?? 0 }}</span>
        </a>
    @endforeach
</nav>

@forelse($messages as $m)
    @php
        [$label, $tone] = ContactMessage::STATUSES[$m->status] ?? [$m->status, 'secondary'];
        $order = $m->order();
        $wa = $m->phone ? preg_replace('/^0/', '62', preg_replace('/\D/', '', $m->phone)) : null;
        $subject = rawurlencode('Re: ' . (ContactMessage::TOPICS[$m->topic] ?? 'Pesan Anda') . ' — ' . config('flore.store.name', 'Floré'));
    @endphp
    <div class="panel mb-3 {{ $m->status === 'new' ? 'message-new' : '' }}">
        <div class="d-flex flex-wrap justify-content-between gap-2">
            <div>
                <div class="fw-semibold">{{ $m->name }} <span class="small text-muted fw-normal">· {{ $m->email }}{{ $m->phone ? ' · ' . $m->phone : '' }}</span></div>
                <div class="small text-muted">
                    {{ ContactMessage::TOPICS[$m->topic] ?? $m->topic }} · {{ $m->created_at->locale('id')->diffForHumans() }}
                    @if($m->user) · customer terdaftar @endif
                    @if($m->order_number)
                        · Pesanan
                        @if($order) <a href="{{ route('admin.orders.show', $order) }}">{{ $m->order_number }}</a>
                        @else {{ $m->order_number }} <span class="text-danger">(tidak cocok dengan akun pengirim)</span> @endif
                    @endif
                </div>
            </div>
            <span class="badge-soft {{ $tone }} align-self-start">{{ $label }}</span>
        </div>
        <p class="my-3" style="white-space: pre-line">{{ $m->message }}</p>
        <div class="d-flex flex-wrap gap-2 align-items-start">
            <a href="mailto:{{ $m->email }}?subject={{ $subject }}" class="btn btn-sm btn-outline-plum">Balas via Email</a>
            @if($wa) <a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-plum">WhatsApp</a> @endif
            <form method="POST" action="{{ route('admin.messages.update', $m) }}" class="d-flex flex-wrap gap-2 ms-auto">
                @csrf @method('PATCH')
                <input name="admin_note" maxlength="1000" value="{{ $m->admin_note }}" placeholder="Catatan internal" class="form-control form-control-sm" style="width: 220px" aria-label="Catatan internal">
                <select name="status" class="form-select form-select-sm" style="width: auto" aria-label="Status">
                    @foreach(ContactMessage::STATUSES as $k => [$l]) <option value="{{ $k }}" @selected($m->status === $k)>{{ $l }}</option> @endforeach
                </select>
                <button class="btn btn-sm btn-plum">Simpan</button>
            </form>
        </div>
    </div>
@empty
    <div class="panel text-center text-muted py-5 small">Belum ada pesan.</div>
@endforelse
<div class="mt-3">{{ $messages->links() }}</div>
@endsection
