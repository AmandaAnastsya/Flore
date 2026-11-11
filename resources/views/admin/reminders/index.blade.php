@extends('layouts.admin')
@section('title', 'Smart Reminder')

@section('content')
@php use App\Models\Reminder; @endphp

<div class="admin-head d-flex flex-wrap justify-content-between align-items-end gap-2">
    <div>
        <h1 class="h3 mb-1">Smart Reminder Logs</h1>
        <p>Pengingat dijalankan otomatis setiap hari pukul 08:00 oleh Laravel Task Scheduler.</p>
    </div>
    <form method="POST" action="{{ route('admin.reminders.run') }}">
        @csrf
        <button class="btn btn-plum">Jalankan Sekarang</button>
    </form>
</div>

@if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

<div class="row g-3 mb-4">
    @foreach(['pending' => 'Menunggu', 'sent' => 'Terkirim', 'failed' => 'Gagal'] as $key => $label)
        <div class="col-4">
            <a href="{{ route('admin.reminders.index', ['status' => $status === $key ? null : $key]) }}" class="text-decoration-none">
                <div class="kpi {{ $status === $key ? 'accent' : '' }}">
                    <div class="label">{{ $label }}</div>
                    <div class="value">{{ $counts[$key] ?? 0 }}</div>
                </div>
            </a>
        </div>
    @endforeach
</div>

{{-- Yang akan dikirim --}}
<h2 class="section-title h6 mb-2">Jadwal Pengingat Mendatang</h2>
<div class="card mb-4">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Customer</th><th>Momen</th><th>Tanggal momen</th><th>Email dikirim</th></tr></thead>
            <tbody>
            @forelse($upcoming as $m)
                <tr>
                    <td>
                        {{ $m['user']->name }}
                        <div class="small text-muted">{{ $m['user']->email }}</div>
                    </td>
                    <td>
                        <div class="small text-muted">{{ Reminder::TYPE_LABELS[$m['type']] ?? $m['type'] }}</div>
                        {{ $m['title'] }}
                    </td>
                    <td>{{ $m['occasion_date']->format('d M Y') }}</td>
                    <td>
                        {{ $m['remind_at']->format('d M Y') }}
                        <div class="small text-muted">{{ (int) today()->diffInDays($m['remind_at']) }} hari lagi</div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-4">
                    Belum ada customer yang mengisi tanggal ulang tahun, anniversary, atau momen spesial.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Riwayat --}}
<h2 class="section-title h6 mb-2">Riwayat Pengiriman</h2>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr><th>Customer</th><th>Momen</th><th>Tanggal momen</th><th>Jadwal kirim</th><th>Status</th></tr>
            </thead>
            <tbody>
            @forelse($reminders as $r)
                <tr>
                    <td>
                        {{ $r->user?->name ?? '—' }}
                        <div class="small text-muted">{{ $r->user?->email }}</div>
                    </td>
                    <td>
                        <div class="small text-muted">{{ Reminder::TYPE_LABELS[$r->type] ?? $r->type }}</div>
                        {{ $r->title }}
                    </td>
                    <td>{{ $r->occasion_date->format('d M Y') }}</td>
                    <td>
                        {{ $r->remind_at->format('d M Y') }}
                        @if($r->sent_at) <div class="small text-muted">Terkirim {{ $r->sent_at->format('d M H:i') }}</div> @endif
                    </td>
                    <td>
                        <span class="badge-soft {{ Reminder::STATUS_BADGES[$r->status] ?? 'secondary' }}">{{ ucfirst($r->status) }}</span>
                        @if($r->error_message) <div class="small text-danger mt-1">{{ Str::limit($r->error_message, 80) }}</div> @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada pengingat yang jatuh tempo.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $reminders->links() }}</div>
@endsection
