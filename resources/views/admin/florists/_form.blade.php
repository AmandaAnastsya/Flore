<div class="mb-2">
    <label for="name" class="form-label small mb-1">Nama florist</label>
    <input id="name" name="name" maxlength="80" required value="{{ old('name', $florist->name) }}" class="form-control @error('name') is-invalid @enderror">
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="mb-2">
    <label for="phone" class="form-label small mb-1">Nomor HP <span class="text-muted">(opsional)</span></label>
    <input id="phone" name="phone" inputmode="tel" value="{{ old('phone', $florist->phone) }}" placeholder="081234567890" class="form-control @error('phone') is-invalid @enderror">
    @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="mb-2">
    <label for="specialty" class="form-label small mb-1">Keahlian <span class="text-muted">(opsional)</span></label>
    <input id="specialty" name="specialty" maxlength="120" value="{{ old('specialty', $florist->specialty) }}" placeholder="mis. Wedding & bloom box" class="form-control">
</div>
<div class="form-check mb-3">
    <input type="hidden" name="is_active" value="0">
    <input id="is_active" type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $florist->is_active))>
    <label for="is_active" class="form-check-label small">Aktif (bisa ditugaskan)</label>
</div>
