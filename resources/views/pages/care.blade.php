@extends('layouts.app')
@section('title', 'Flower Care Guide')

@section('content')
<div class="container py-4 info-page">
    @include('pages._hero', ['title' => 'Flower Care Guide', 'subtitle' => 'Panduan merawat bunga potong agar segar & tahan lama hingga 10 hari.', 'tone' => 'sage'])

    <h2 class="section-title h5 mb-3">4 Langkah Mudah Perawatan Bunga Potong</h2>
    <ol class="care-steps mb-5">
        @foreach([
            ['Potong Batang 45°', 'Potong bagian bawah batang sepanjang 2 cm secara miring dengan pisau tajam setiap 2 hari agar penyerapan air maksimal.'],
            ['Gunakan Air Bersih', 'Gunakan vas yang sangat bersih dan isi dengan air dingin / suhu ruangan. Tambahkan flower food yang kami sertakan.'],
            ['Bersihkan Daun Bawah', 'Buang semua daun yang terendam air di dalam vas untuk mencegah pertumbuhan bakteri yang mempercepat pembusukan.'],
            ['Hindari Sinar Langsung', 'Letakkan vas di area sejuk. Hindari sinar matahari langsung, hembusan AC berlebih, atau buah-buahan matang di dekatnya.'],
        ] as $i => [$t, $d])
            <li>
                <span class="step-no" aria-hidden="true">{{ $i + 1 }}</span>
                <div><h3>{{ $i + 1 }}. {{ $t }}</h3><p class="mb-0">{{ $d }}</p></div>
            </li>
        @endforeach
    </ol>

    <h2 class="section-title h5 mb-3">Tips Khusus Berdasarkan Jenis Bunga</h2>
    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0 care-table">
                <thead><tr><th>Jenis Bunga</th><th>Estimasi Ketahanan</th><th>Trik Khusus Perawatan</th></tr></thead>
                <tbody>
                @foreach([
                    ['Mawar (Rose)', '7 – 10 hari', 'Buang kelopak luar yang layu; potong batang di bawah air.'],
                    ['Peony', '5 – 7 hari', 'Bantu kuncup mekar dengan membilasnya menggunakan air hangat.'],
                    ['Lily', '10 – 14 hari', 'Petik serbuk sari (anther) kuning agar tidak mengotori kelopak.'],
                    ['Hydrangea', '4 – 7 hari', 'Semprotkan air halus pada kepala bunga jika mulai layu.'],
                    ['Tulip', '5 – 7 hari', 'Batang tetap memanjang di vas — potong sedikit & gunakan air dingin.'],
                    ['Gerbera', '5 – 7 hari', 'Cukup air dangkal (±5 cm) agar batang tidak cepat membusuk.'],
                    ['Matahari (Sunflower)', '6 – 10 hari', 'Butuh banyak air; isi ulang vas setiap hari.'],
                    ["Baby's Breath", '7 – 10 hari', 'Ganti air setiap 2 hari; jauhkan dari panas langsung.'],
                    ['Anyelir & Krisan', '14 – 21 hari', 'Sangat tahan lama — cukup ganti air dan potong batang rutin.'],
                ] as [$name, $life, $tip])
                    <tr><td class="fw-semibold">{{ $name }}</td><td class="text-success fw-semibold">{{ $life }}</td><td>{{ $tip }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <p class="small text-muted mb-4">Ketahanan bisa berbeda tergantung suhu ruangan dan kesegaran saat bunga tiba. Setiap pesanan Floré dikirim dari stok yang baru datang ke studio.</p>

    <div class="text-center">
        <a href="{{ route('stems.index') }}" class="btn btn-plum me-2">Beli Bunga per Tangkai</a>
        <a href="{{ route('pages.faq') }}" class="btn btn-outline-plum">FAQ & Garansi</a>
    </div>
</div>
@endsection
