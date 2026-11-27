@extends('layouts.admin')
@section('title', 'Biaya Operasional')

@section('content')
@php
    use App\Models\Expense;
    $rp = fn ($v) => 'Rp ' . number_format($v, 0, ',', '.');
@endphp

@include('admin.reports._header', ['title' => 'Biaya Operasional', 'subtitle' => 'Gaji, sewa, listrik, transportasi, dan biaya lain di luar harga modal bunga — dipakai untuk menghitung Laba Bersih.'])

<div class="row g-4">
    <div class="col-lg-8">
        <div class="row g-3 mb-3">
            <div class="col-sm-6"><div class="kpi accent">
                <div class="label">Total Biaya Periode Ini</div>
                <div class="value">{{ $rp($total) }}</div>
                <div class="note text-muted">{{ $expenses->total() }} catatan</div>
            </div></div>
            <div class="col-sm-6"><div class="kpi">
                <div class="label">Pos Biaya Terbesar</div>
                <div class="value" style="font-size:1.1rem">{{ $byCategory->keys()->first() ? Expense::CATEGORIES[$byCategory->keys()->first()] : '—' }}</div>
                <div class="note text-muted">{{ $byCategory->first() ? $rp($byCategory->first()) : 'belum ada biaya' }}</div>
            </div></div>
        </div>

        @if($byCategory->isNotEmpty())
            <div class="panel mb-3">
                @foreach($byCategory as $cat => $amount)
                    <div class="d-flex justify-content-between small mb-1"><span>{{ Expense::CATEGORIES[$cat] ?? $cat }}</span><strong>{{ $rp($amount) }}</strong></div>
                    <div class="bar mb-2"><span style="width: {{ $total ? round($amount / $total * 100) : 0 }}%"></span></div>
                @endforeach
            </div>
        @endif

        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Tanggal</th><th>Kategori</th><th>Keterangan</th><th class="text-end">Jumlah</th><th></th></tr></thead>
                    <tbody>
                    @forelse($expenses as $e)
                        <tr>
                            <td class="small">{{ $e->spent_on->format('d M Y') }}</td>
                            <td class="small">{{ Expense::CATEGORIES[$e->category] ?? $e->category }}</td>
                            <td>{{ $e->description }} <div class="small text-muted">{{ $e->user?->name }}</div></td>
                            <td class="text-end fw-semibold">{{ $rp($e->amount) }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.reports.expenses.destroy', $e) }}" onsubmit="return confirm('Hapus catatan biaya ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-link text-danger p-0">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Belum ada biaya operasional pada periode ini.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $expenses->links() }}</div>
    </div>

    <div class="col-lg-4">
        <div class="panel">
            <h2 class="panel-title">Catat Biaya</h2>
            <form method="POST" action="{{ route('admin.reports.expenses.store') }}">
                @csrf
                <div class="mb-2">
                    <label for="spent_on" class="form-label small mb-1">Tanggal</label>
                    <input id="spent_on" type="date" name="spent_on" max="{{ today()->toDateString() }}" value="{{ old('spent_on', today()->toDateString()) }}" required class="form-control @error('spent_on') is-invalid @enderror">
                    @error('spent_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-2">
                    <label for="category" class="form-label small mb-1">Kategori</label>
                    <select id="category" name="category" class="form-select @error('category') is-invalid @enderror">
                        @foreach(Expense::CATEGORIES as $k => $label) <option value="{{ $k }}" @selected(old('category') === $k)>{{ $label }}</option> @endforeach
                    </select>
                </div>
                <div class="mb-2">
                    <label for="description" class="form-label small mb-1">Keterangan</label>
                    <input id="description" name="description" maxlength="200" required value="{{ old('description') }}" placeholder="mis. Gaji florist November" class="form-control @error('description') is-invalid @enderror">
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label for="amount" class="form-label small mb-1">Jumlah (Rp)</label>
                    <input id="amount" type="number" name="amount" min="1" required value="{{ old('amount') }}" class="form-control @error('amount') is-invalid @enderror">
                    @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <button class="btn btn-plum w-100">Simpan Biaya</button>
            </form>
            <p class="small text-muted mt-3 mb-0">Pembelian bunga & bahan <strong>tidak</strong> dicatat di sini — sudah masuk lewat restock di Inventory Hub dan HPP tiap pesanan, agar tidak terhitung dua kali.</p>
        </div>
    </div>
</div>
@endsection
