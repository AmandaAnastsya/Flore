{{-- Pilih kurir + estimasi waktu tempuh. Dipakai untuk "Tugaskan Kurir" dan "Re-route". --}}
@php $uid = $minutesFromNow ? 'reroute' : 'assign'; @endphp
<form method="POST" action="{{ $route }}" class="row g-2 align-items-end mt-1">
    @csrf
    <div class="col-sm-6">
        <label for="courier_id_{{ $uid }}" class="form-label small mb-1">Kurir</label>
        <select id="courier_id_{{ $uid }}" name="courier_id" class="form-select form-select-sm" required @disabled($couriers->isEmpty())>
            @foreach($couriers as $c)
                <option value="{{ $c->id }}" @selected(old('courier_id', $o?->courier_id) == $c->id)>{{ $c->name }} — {{ $c->vehicleLabel() }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-6">
        <label for="travel_minutes_{{ $uid }}" class="form-label small mb-1">{{ $minutesFromNow ? 'Tiba dalam' : 'Estimasi tempuh' }}</label>
        <select id="travel_minutes_{{ $uid }}" name="travel_minutes" class="form-select form-select-sm" required>
            @foreach(\App\Models\Courier::TRAVEL_OPTIONS as $m)
                <option value="{{ $m }}" @selected(old('travel_minutes', $o?->travel_minutes ?? 45) == $m)>± {{ $m }} menit</option>
            @endforeach
        </select>
    </div>
    @error('courier_id') <div class="col-12 small text-danger">{{ $message }}</div> @enderror
    @error('travel_minutes') <div class="col-12 small text-danger">{{ $message }}</div> @enderror
    <div class="col-12">
        <button class="btn btn-sm btn-outline-plum w-100" @disabled($couriers->isEmpty())>{{ $button }}</button>
    </div>
</form>
