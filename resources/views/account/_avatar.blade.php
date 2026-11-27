{{-- Foto profil (customer & admin). $r = fn(nama route) => URL sesuai area --}}
<div class="d-flex align-items-center gap-3 flex-wrap">
    <div class="avatar-lg" aria-hidden="true">
        @if($user->avatarUrl()) <img src="{{ $user->avatarUrl() }}" alt=""> @else {{ $user->initials() }} @endif
    </div>
    <div>
        <form method="POST" action="{{ $r('account.avatar.update') }}" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-center">
            @csrf
            <label for="avatar" class="visually-hidden">Pilih foto profil</label>
            <input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required
                   class="form-control form-control-sm @error('avatar') is-invalid @enderror" style="max-width: 240px">
            <button class="btn btn-sm btn-outline-plum">{{ $user->avatar ? 'Ganti Foto' : 'Unggah Foto' }}</button>
            @error('avatar') <div class="invalid-feedback d-block w-100">{{ $message }}</div> @enderror
        </form>
        <div class="small text-muted mt-1">JPG, PNG, atau WEBP maks. 3 MB — otomatis dipotong persegi.</div>
        @if($user->avatar)
            <form method="POST" action="{{ $r('account.avatar.destroy') }}" class="mt-1">
                @csrf @method('DELETE')
                <button class="btn btn-link btn-sm p-0 text-danger">Hapus foto</button>
            </form>
        @endif
    </div>
</div>
