@extends('layouts.admin')
@section('title', 'Florist & Produksi')

@section('content')
@php
    use App\Models\Order;
    $columns = ['queue' => 'Menunggu Dirangkai'] + Order::STAGES;
@endphp

<div class="admin-head">
    <h1 class="h3 mb-1">Florist Assignment & Production Board</h1>
    <p>Pantau setiap pesanan dari mulai dirangkai, QC, packing, sampai siap diserahkan ke kurir.</p>
</div>

@include('catalog._flash')

{{-- Papan produksi --}}
<div class="production-board mb-4">
    @foreach($columns as $key => $label)
        @php $cards = $board->get($key, collect()); @endphp
        <section class="board-col" aria-label="{{ $label }}">
            <h2>{{ $label }} <span class="tab-count">{{ $cards->count() }}</span></h2>
            @forelse($cards as $o)
                <a href="{{ route('admin.orders.show', $o) }}" class="board-card">
                    <div class="d-flex justify-content-between gap-2">
                        <strong class="small">#{{ $o->order_number }}</strong>
                        <span class="small {{ $o->delivery_date->isToday() ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $o->delivery_date->isToday() ? 'Hari ini' : $o->delivery_date->format('d M') }}</span>
                    </div>
                    <div class="small">{{ $o->items->map(fn ($i) => $i->quantity . '× ' . $i->name)->implode(', ') }}</div>
                    <div class="small {{ $o->florist ? 'text-mauve' : 'text-danger' }}">{{ $o->florist ? 'Florist: ' . $o->florist->name : 'Belum ada florist' }}</div>
                    @if($o->production_note) <div class="small text-muted fst-italic">“{{ \Illuminate\Support\Str::limit($o->production_note, 60) }}”</div> @endif
                </a>
            @empty
                <div class="small text-muted py-2">Kosong</div>
            @endforelse
        </section>
    @endforeach
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <h2 class="section-title h6 mb-2">Tim Florist</h2>
        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Florist</th><th>Keahlian</th><th class="text-center">Sedang dikerjakan</th><th class="text-center">Selesai</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse($florists as $f)
                        <tr class="{{ $f->is_active ? '' : 'text-muted' }}">
                            <td><div class="fw-semibold">{{ $f->name }}</div><div class="small text-muted">{{ $f->phone ?? '—' }}</div></td>
                            <td class="small">{{ $f->specialty ?? '—' }}</td>
                            <td class="text-center">{{ $f->active_count }}</td>
                            <td class="text-center">{{ $f->done_count }}</td>
                            <td><span class="badge-soft {{ $f->is_active ? 'success' : 'secondary' }}">{{ $f->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="text-end"><a href="{{ route('admin.florists.edit', $f) }}" class="btn btn-sm btn-outline-plum">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada florist. Tambahkan lewat formulir di samping.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel">
            <h2 class="panel-title">Tambah Florist</h2>
            <form method="POST" action="{{ route('admin.florists.store') }}">
                @csrf
                @include('admin.florists._form')
                <button class="btn btn-plum w-100">Simpan Florist</button>
            </form>
        </div>
    </div>
</div>
@endsection
