{{-- Indikator alur belanja (mockup proposal hal. 43). Pakai: @include('checkout._steps', ['step' => 2]) --}}
<ol class="flow-steps" aria-label="Langkah checkout">
    @foreach(['Keranjang', 'Pengiriman & Kartu', 'Tinjau Pesanan', 'Pembayaran'] as $i => $label)
        @php $n = $i + 1; @endphp
        <li class="{{ $n < $step ? 'done' : ($n === $step ? 'current' : '') }}" @if($n === $step) aria-current="step" @endif>
            {{ $n }}. {{ $label }}
        </li>
    @endforeach
</ol>
