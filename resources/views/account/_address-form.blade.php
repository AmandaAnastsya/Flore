{{-- Field alamat (dipakai halaman Buku Alamat). $address --}}
<div class="row g-3">
    <div class="col-md-5">
        <label for="label" class="form-label small mb-1">Nama alamat</label>
        <input id="label" name="label" maxlength="40" required value="{{ old('label', $address->label) }}" placeholder="mis. Rumah Orang Tua"
               class="form-control @error('label') is-invalid @enderror">
        @error('label') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-7">
        <label for="recipient_name" class="form-label small mb-1">Nama penerima</label>
        <input id="recipient_name" name="recipient_name" maxlength="100" required value="{{ old('recipient_name', $address->recipient_name) }}"
               class="form-control @error('recipient_name') is-invalid @enderror">
        @error('recipient_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-5">
        <label for="recipient_phone" class="form-label small mb-1">Telepon penerima</label>
        <input id="recipient_phone" name="recipient_phone" inputmode="tel" required value="{{ old('recipient_phone', $address->recipient_phone) }}" placeholder="081234567890"
               class="form-control @error('recipient_phone') is-invalid @enderror">
        @error('recipient_phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-7">
        <label for="city" class="form-label small mb-1">Kota</label>
        <input id="city" name="city" maxlength="100" required value="{{ old('city', $address->city) }}" class="form-control @error('city') is-invalid @enderror">
        @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label for="address" class="form-label small mb-1">Alamat lengkap</label>
        <textarea id="address" name="address" rows="2" maxlength="500" required class="form-control @error('address') is-invalid @enderror">{{ old('address', $address->address) }}</textarea>
        @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12 form-check ms-2">
        <input type="hidden" name="is_default" value="0">
        <input id="is_default" type="checkbox" name="is_default" value="1" class="form-check-input" @checked(old('is_default', $address->is_default))>
        <label for="is_default" class="form-check-label small">Jadikan alamat utama (otomatis terisi saat checkout)</label>
    </div>
</div>
