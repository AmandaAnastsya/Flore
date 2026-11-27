@extends('layouts.admin')
@section('title', 'Kelola Kurir')

@section('content')
<nav class="mb-3 small" aria-label="Breadcrumb">
    <a href="{{ route('admin.logistics.index') }}" class="text-decoration-none text-mauve">&larr; Logistik & Kurir</a>
</nav>

<div class="admin-head">
    <h1 class="h3 mb-1">Armada Kurir</h1>
    <p>Kurir aktif bisa ditugaskan ke pesanan di halaman Logistics & Courier Dispatch.</p>
</div>

@include('catalog._flash')

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Kurir</th><th>Armada</th><th class="text-center">Ditugaskan</th><th class="text-center">Di jalan</th><th class="text-center">Terkirim</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse($couriers as $c)
                        <tr class="{{ $c->is_active ? '' : 'text-muted' }}">
                            <td>
                                <div class="fw-semibold">{{ $c->name }}</div>
                                <a href="{{ $c->whatsappUrl() }}" target="_blank" rel="noopener" class="small">{{ $c->phone }}</a>
                                @if($c->notes) <div class="small text-muted">{{ $c->notes }}</div> @endif
                            </td>
                            <td>{{ $c->vehicleLabel() }} @if($c->plate_number) <div class="small text-muted">{{ $c->plate_number }}</div> @endif</td>
                            <td class="text-center">{{ $c->assigned_count }}</td>
                            <td class="text-center">{{ $c->on_the_way_count }}</td>
                            <td class="text-center">{{ $c->delivered_count }}</td>
                            <td><span class="badge-soft {{ $c->is_active ? 'success' : 'secondary' }}">{{ $c->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="text-end"><a href="{{ route('admin.couriers.edit', $c) }}" class="btn btn-sm btn-outline-plum">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada kurir. Tambahkan lewat formulir di samping.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="panel">
            <h2 class="panel-title">Tambah Kurir</h2>
            <form method="POST" action="{{ route('admin.couriers.store') }}">
                @csrf
                @include('admin.couriers._form')
                <button class="btn btn-plum w-100">Simpan Kurir</button>
            </form>
        </div>
    </div>
</div>
@endsection
