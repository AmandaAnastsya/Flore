@extends('layouts.admin')
@section('title', 'Best-selling Products')

@section('content')
@php $rp = fn ($v) => 'Rp ' . number_format($v, 0, ',', '.'); @endphp

@include('admin.reports._header', ['title' => 'Best-selling Products', 'subtitle' => 'Produk paling laku dan kontribusinya terhadap pendapatan.'])

@foreach(['Bouquet' => $bouquets, 'Bunga Per Tangkai' => $flowers] as $heading => $rows)
    <h2 class="section-title h6 mb-2 {{ $loop->first ? '' : 'mt-4' }}">{{ $heading }}</h2>
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th style="width:3rem">#</th><th>Produk</th><th class="text-end">Terjual</th><th class="text-end">Pesanan</th><th class="text-end">Pendapatan</th><th style="width:28%">Kontribusi</th></tr>
                </thead>
                <tbody>
                @forelse($rows as $i => $r)
                    <tr>
                        <td class="text-muted">{{ $i + 1 }}</td>
                        <td class="fw-semibold">{{ $r['name'] }}</td>
                        <td class="text-end">{{ $r['qty'] }}{{ $heading === 'Bouquet' ? '' : ' tangkai' }}</td>
                        <td class="text-end">{{ $r['orders'] }}</td>
                        <td class="text-end">{{ $rp($r['revenue']) }}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="bar flex-grow-1" role="img" aria-label="{{ $r['share'] }} persen"><span style="width: {{ $r['share'] }}%"></span></div>
                                <span class="small text-muted" style="min-width:3rem">{{ $r['share'] }}%</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada penjualan lunas pada periode ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endforeach
@endsection
