{{-- Form ganti password (dipakai halaman customer & admin). $action, $min --}}
<form method="POST" action="{{ $action }}" novalidate data-password-form>
    @csrf @method('PUT')

    <div class="mb-3">
        <label for="current_password" class="form-label">Password saat ini</label>
        <div class="input-group">
            <input id="current_password" type="password" name="current_password" autocomplete="current-password" required
                   class="form-control @error('current_password') is-invalid @enderror">
            <button type="button" class="btn btn-outline-secondary" data-toggle-password="current_password" aria-label="Tampilkan password saat ini">Lihat</button>
            @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>

    <div class="mb-2">
        <label for="password" class="form-label">Password baru</label>
        <div class="input-group">
            <input id="password" type="password" name="password" autocomplete="new-password" required minlength="{{ $min }}"
                   aria-describedby="password-rules" class="form-control @error('password') is-invalid @enderror">
            <button type="button" class="btn btn-outline-secondary" data-toggle-password="password" aria-label="Tampilkan password baru">Lihat</button>
            @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <ul id="password-rules" class="password-rules small mb-3" aria-live="polite">
        <li data-rule="length">Minimal {{ $min }} karakter</li>
        <li data-rule="letter">Mengandung huruf</li>
        <li data-rule="number">Mengandung angka</li>
    </ul>

    <div class="mb-4">
        <label for="password_confirmation" class="form-label">Ulangi password baru</label>
        <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required class="form-control">
        <div class="form-text" data-match-hint></div>
    </div>

    <button class="btn btn-plum">Simpan Password Baru</button>
    <p class="small text-muted mt-3 mb-0">Setelah diganti, semua sesi login di perangkat lain akan dikeluarkan dan kami mengirim email pemberitahuan.</p>
</form>

@push('scripts')
<script>
    (() => {
        const form = document.querySelector('[data-password-form]');
        if (!form) return;
        const pw = form.querySelector('#password');
        const confirm = form.querySelector('#password_confirmation');
        const hint = form.querySelector('[data-match-hint]');
        const min = {{ $min }};
        const rules = {
            length: (v) => v.length >= min,
            letter: (v) => /[A-Za-z]/.test(v),
            number: (v) => /\d/.test(v),
        };
        const update = () => {
            for (const [key, test] of Object.entries(rules)) {
                form.querySelector(`[data-rule="${key}"]`).classList.toggle('ok', test(pw.value));
            }
            hint.textContent = confirm.value === '' ? '' : (confirm.value === pw.value ? '✓ Password cocok' : 'Password belum sama');
            hint.className = 'form-text ' + (confirm.value && confirm.value === pw.value ? 'text-success' : 'text-danger');
        };
        pw.addEventListener('input', update);
        confirm.addEventListener('input', update);
        form.querySelectorAll('[data-toggle-password]').forEach((btn) => btn.addEventListener('click', () => {
            const input = form.querySelector('#' + btn.dataset.togglePassword);
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Sembunyi' : 'Lihat';
        }));
        update();
    })();
</script>
@endpush
