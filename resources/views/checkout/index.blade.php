@extends('layouts.app')

@section('content')
@include('catalog._styles')
@php
    $v = fn ($key, $default = '') => old($key, $draft[$key] ?? $default);
    $anon = $errors->any() ? old('is_anonymous') : ($draft['is_anonymous'] ?? false);
    $chosenSlot = $v('delivery_slot');
@endphp

<div class="container py-4 catalog">
    <h1 class="h4 section-title mb-3">Checkout</h1>
    @include('checkout._steps', ['step' => 2])
    @include('catalog._flash')

    <form method="POST" action="{{ route('checkout.store') }}" novalidate>
        @csrf
        <div class="row g-4">
            <div class="col-lg-8">

                {{-- 1. Penerima --}}
                <div class="card border-0 shadow-sm mb-4"><div class="card-body">
                    <h2 class="h6 section-title mb-3">1. Data Penerima</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="recipient_name">Nama penerima</label>
                            <input type="text" id="recipient_name" name="recipient_name" maxlength="100"
                                   value="{{ $v('recipient_name') }}" class="form-control @error('recipient_name') is-invalid @enderror">
                            @error('recipient_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="recipient_phone">No. telepon penerima</label>
                            <input type="tel" id="recipient_phone" name="recipient_phone" placeholder="081234567890"
                                   value="{{ $v('recipient_phone') }}" class="form-control @error('recipient_phone') is-invalid @enderror">
                            @error('recipient_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="delivery_city">Kota</label>
                            <input type="text" id="delivery_city" name="delivery_city" maxlength="100"
                                   value="{{ $v('delivery_city') }}" class="form-control @error('delivery_city') is-invalid @enderror">
                            @error('delivery_city') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="delivery_address">Alamat lengkap</label>
                            <textarea id="delivery_address" name="delivery_address" rows="2" maxlength="500"
                                      class="form-control @error('delivery_address') is-invalid @enderror">{{ $v('delivery_address') }}</textarea>
                            @error('delivery_address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div></div>

                {{-- 2. Jadwal --}}
                <div class="card border-0 shadow-sm mb-4"><div class="card-body">
                    <h2 class="h6 section-title mb-3">2. Slot Waktu Pengiriman</h2>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label" for="delivery_date">Tanggal</label>
                            <input type="date" id="delivery_date" name="delivery_date"
                                   min="{{ $minDate }}" max="{{ $maxDate }}" value="{{ $v('delivery_date') }}"
                                   class="form-control @error('delivery_date') is-invalid @enderror">
                            @error('delivery_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">
                                @if($sameDayOk)
                                    Semua bouquet di keranjangmu bisa dikirim hari ini (min. {{ \App\Services\DeliverySchedule::LEAD_HOURS }} jam dari sekarang).
                                @else
                                    Pengiriman paling cepat besok (ada bouquet yang tidak same-day).
                                @endif
                            </div>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label d-block">Jam</label>
                            @foreach($slots as $key => $label)
                                <div class="form-check">
                                    <input class="form-check-input @error('delivery_slot') is-invalid @enderror" type="radio"
                                           name="delivery_slot" id="slot_{{ $key }}" value="{{ $key }}" @checked($chosenSlot === $key)>
                                    <label class="form-check-label" for="slot_{{ $key }}">{{ $label }}</label>
                                </div>
                            @endforeach
                            @error('delivery_slot') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div></div>

                {{-- 3. Kartu ucapan --}}
                <div class="card border-0 shadow-sm mb-4"><div class="card-body">
                    <h2 class="h6 section-title mb-3">3. Pesan Kartu Ucapan <span class="text-muted fw-normal">(opsional)</span></h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="occasion">Occasion</label>
                            <select id="occasion" name="occasion" class="form-select @error('occasion') is-invalid @enderror">
                                <option value="">— Pilih —</option>
                                @foreach($occasions as $o)
                                    <option value="{{ $o }}" @selected($v('occasion') === $o)>{{ ucfirst(str_replace('-', ' ', $o)) }}</option>
                                @endforeach
                            </select>
                            @error('occasion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="card_message">Pesan</label>
                            <textarea id="card_message" name="card_message" rows="3" maxlength="200"
                                      class="form-control @error('card_message') is-invalid @enderror">{{ $v('card_message') }}</textarea>
                            <div class="d-flex justify-content-between">
                                @error('card_message') <div class="text-danger small">{{ $message }}</div> @else <span></span> @enderror
                                <small class="text-muted"><span id="card_count">0</span>/200</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="sender_name">Nama pengirim di kartu</label>
                            <input type="text" id="sender_name" name="sender_name" maxlength="100"
                                   value="{{ $v('sender_name', $user->name) }}" class="form-control @error('sender_name') is-invalid @enderror">
                            @error('sender_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_anonymous" value="1" id="is_anonymous" @checked($anon)>
                                <label class="form-check-label" for="is_anonymous">Kirim secara anonim</label>
                            </div>
                        </div>
                    </div>
                </div></div>
            </div>

            {{-- Ringkasan --}}
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm"><div class="card-body">
                    <h2 class="h6 section-title mb-3">Ringkasan Pesanan</h2>
                    @foreach($lines as $line)
                        <div class="d-flex justify-content-between small mb-2">
                            <span>{{ $line['bouquet']->name }} × {{ $line['quantity'] }}</span>
                            <span>Rp {{ number_format($line['subtotal'], 0, ',', '.') }}</span>
                        </div>
                    @endforeach
                    @foreach($stemLines as $line)
                        <div class="d-flex justify-content-between small mb-2">
                            <span>{{ $line['flower']->name }} × {{ $line['quantity'] }} tangkai</span>
                            <span>Rp {{ number_format($line['subtotal'], 0, ',', '.') }}</span>
                        </div>
                    @endforeach
                    <hr>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Subtotal</span><strong class="price">Rp {{ number_format($subtotal, 0, ',', '.') }}</strong>
                    </div>
                    <div class="small text-muted mb-3">Ongkir dikonfirmasi saat pesanan dibuat.</div>
                    <button class="btn btn-rose w-100 mb-2">Tinjau pesanan</button>
                    <a href="{{ route('cart.index') }}" class="btn btn-outline-secondary w-100">Kembali ke keranjang</a>
                </div></div>
            </div>
        </div>
    </form>
</div>

<script>
    const msg = document.getElementById('card_message');
    const count = document.getElementById('card_count');
    const updateCount = () => count.textContent = msg.value.length;
    msg.addEventListener('input', updateCount); updateCount();

    const anon = document.getElementById('is_anonymous');
    const sender = document.getElementById('sender_name');
    const syncAnon = () => sender.disabled = anon.checked;
    anon.addEventListener('change', syncAnon); syncAnon();

    // slot yang sudah lewat tidak bisa dipilih untuk hari ini
    const dateEl = document.getElementById('delivery_date');
    const today = @json($today);
    const todaySlots = @json($todaySlots);
    function syncSlots() {
        document.querySelectorAll('input[name=delivery_slot]').forEach(r => {
            const off = dateEl.value === today && !todaySlots.includes(r.value);
            r.disabled = off;
            if (off && r.checked) r.checked = false;
            r.closest('.form-check').classList.toggle('opacity-50', off);
        });
    }
    dateEl.addEventListener('change', syncSlots); syncSlots();
</script>
@endsection