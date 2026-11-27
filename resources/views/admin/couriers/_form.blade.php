@php use App\Models\Courier; @endphp
<div class="mb-2">
    <label for="name" class="form-label small mb-1">Nama kurir</label>
    <input id="name" name="name" maxlength="80" required class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $courier->name) }}">
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="mb-2">
    <label for="phone" class="form-label small mb-1">Nomor HP / WhatsApp</label>
    <input id="phone" name="phone" inputmode="tel" required class="form-control @error('phone') is-invalid @enderror"
           value="{{ old('phone', $courier->phone) }}" placeholder="081234567890">
    @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="row g-2 mb-2">
    <div class="col-sm-7">
        <label for="vehicle" class="form-label small mb-1">Armada</label>
        <select id="vehicle" name="vehicle" class="form-select @error('vehicle') is-invalid @enderror">
            @foreach(Courier::VEHICLES as $key => $label)
                <option value="{{ $key }}" @selected(old('vehicle', $courier->vehicle) === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @error('vehicle') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-sm-5">
        <label for="plate_number" class="form-label small mb-1">Plat nomor</label>
        <input id="plate_number" name="plate_number" maxlength="15" class="form-control @error('plate_number') is-invalid @enderror"
               value="{{ old('plate_number', $courier->plate_number) }}" placeholder="B 1234 XYZ">
        @error('plate_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
<div class="mb-2">
    <label for="notes" class="form-label small mb-1">Catatan <span class="text-muted">(opsional)</span></label>
    <input id="notes" name="notes" maxlength="160" class="form-control @error('notes') is-invalid @enderror"
           value="{{ old('notes', $courier->notes) }}" placeholder="mis. Area Jakarta Selatan, shift pagi">
    @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="form-check mb-3">
    <input type="hidden" name="is_active" value="0">
    <input id="is_active" type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $courier->is_active))>
    <label for="is_active" class="form-check-label small">Aktif (bisa ditugaskan)</label>
</div>
