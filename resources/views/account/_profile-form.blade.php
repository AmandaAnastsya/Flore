{{-- Data diri (customer & admin) --}}
<form method="POST" action="{{ $r('account.profile.update') }}" novalidate data-profile-form>
    @csrf @method('PUT')
    <div class="row g-3">
        <div class="col-md-6">
            <label for="name" class="form-label">Nama lengkap</label>
            <input id="name" name="name" maxlength="255" required autocomplete="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror">
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label for="phone" class="form-label">No. HP / WhatsApp @if($user->isAdmin()) <span class="text-muted small">(opsional)</span> @endif</label>
            <input id="phone" name="phone" inputmode="tel" autocomplete="tel" placeholder="081234567890" value="{{ old('phone', $user->phone) }}" class="form-control @error('phone') is-invalid @enderror">
            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label for="email" class="form-label">Email login</label>
            <input id="email" type="email" name="email" maxlength="255" required autocomplete="email" value="{{ old('email', $user->email) }}"
                   data-original="{{ $user->email }}" class="form-control @error('email') is-invalid @enderror">
            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6" data-email-confirm @unless($errors->has('current_password') || old('email', $user->email) !== $user->email) hidden @endunless>
            <label for="current_password" class="form-label">Password saat ini</label>
            <input id="current_password" type="password" name="current_password" autocomplete="current-password" class="form-control @error('current_password') is-invalid @enderror">
            @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @else <div class="form-text">Wajib diisi untuk mengganti email.</div> @enderror
        </div>
    </div>
    <button class="btn btn-plum mt-4">Simpan Profil</button>
</form>

@push('scripts')
<script>
    // tampilkan kolom password hanya saat email diubah
    (() => {
        const email = document.querySelector('[data-profile-form] #email');
        const box = document.querySelector('[data-email-confirm]');
        if (!email || !box) return;
        email.addEventListener('input', () => { box.hidden = email.value.trim().toLowerCase() === email.dataset.original.toLowerCase(); });
    })();
</script>
@endpush
