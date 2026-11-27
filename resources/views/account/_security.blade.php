{{-- Keamanan: password & perangkat yang sedang login (customer & admin) --}}
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <div class="fw-semibold">Password</div>
        <div class="small text-muted">Ganti password secara berkala, terutama jika pernah login di perangkat umum.</div>
    </div>
    <a href="{{ auth()->user()->isAdmin() ? route('admin.password.edit') : route('account.password.edit') }}" class="btn btn-sm btn-outline-plum">Ganti Password</a>
</div>

<hr>
<div class="fw-semibold mb-1">Perangkat yang sedang login</div>
@if(! $sessionsSupported)
    <p class="small text-muted mb-0">Daftar perangkat hanya tersedia bila SESSION_DRIVER=database.</p>
@else
    <ul class="session-list">
        @forelse($sessions as $s)
            <li>
                <div>
                    <div class="fw-semibold small">{{ $s['device'] }} @if($s['current']) <span class="badge-soft success ms-1">Perangkat ini</span> @endif</div>
                    <div class="small text-muted">IP {{ $s['ip'] ?? '—' }} · aktif {{ $s['last_active']->locale('id')->diffForHumans() }}</div>
                </div>
                @unless($s['current'])
                    <form method="POST" action="{{ $r('account.sessions.destroy', $s['id']) }}">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-link text-danger p-0">Keluarkan</button>
                    </form>
                @endunless
            </li>
        @empty
            <li class="small text-muted">Belum ada data sesi.</li>
        @endforelse
    </ul>

    @if($sessions->where('current', false)->isNotEmpty())
        <form method="POST" action="{{ $r('account.sessions.others') }}" class="d-flex flex-wrap gap-2 align-items-start mt-2">
            @csrf
            <div>
                <label for="sessions_password" class="visually-hidden">Password</label>
                <input id="sessions_password" type="password" name="password" required autocomplete="current-password" placeholder="Password saat ini"
                       class="form-control form-control-sm @error('password', 'sessions') is-invalid @enderror">
                @error('password', 'sessions') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <button class="btn btn-sm btn-outline-secondary">Keluarkan Semua Perangkat Lain</button>
        </form>
    @endif
@endif
