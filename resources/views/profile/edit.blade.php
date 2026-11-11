@extends('layouts.app')
@section('title', 'Profil & Momen Spesial')

@section('content')
@php
    use App\Models\Reminder;
    $fmt = fn ($d) => $d->locale('id')->translatedFormat('d F Y');
    $dayMonth = fn ($d) => $d->locale('id')->translatedFormat('d F');
@endphp

<div class="container">
    <div class="mb-4">
        <div class="eyebrow">My Account</div>
        <h1 class="page-title h3 mb-1">Profil & Momen Spesial</h1>
        <p class="text-muted small mb-0">Simpan tanggal penting — Floré akan mengirim email pengingat sebelum harinya tiba.</p>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif

    <div class="row g-4">
        <div class="col-lg-7">
            {{-- Data diri & momen pribadi --}}
            <form method="POST" action="{{ route('profile.update') }}" class="panel mb-4" novalidate>
                @csrf
                @method('PUT')

                <h2 class="panel-title">Data Diri</h2>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Nama lengkap</label>
                        <input id="name" name="name" value="{{ old('name', $user->name) }}" maxlength="255"
                               class="form-control @error('name') is-invalid @enderror" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label">No. HP</label>
                        <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="081234567890"
                               class="form-control @error('phone') is-invalid @enderror" required>
                        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Email</label>
                        <input value="{{ $user->email }}" class="form-control" disabled>
                        <div class="form-text">Email pengingat dikirim ke alamat ini.</div>
                    </div>
                </div>

                <h2 class="panel-title">Momen Spesialku</h2>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="date_of_birth" class="form-label">Tanggal ulang tahun</label>
                        <input id="date_of_birth" type="date" name="date_of_birth" max="{{ now()->subDay()->toDateString() }}"
                               value="{{ old('date_of_birth', $user->date_of_birth?->toDateString()) }}"
                               class="form-control @error('date_of_birth') is-invalid @enderror">
                        @error('date_of_birth') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="anniversary_date" class="form-label">Tanggal anniversary</label>
                        <input id="anniversary_date" type="date" name="anniversary_date" max="{{ now()->toDateString() }}"
                               value="{{ old('anniversary_date', $user->anniversary_date?->toDateString()) }}"
                               class="form-control @error('anniversary_date') is-invalid @enderror">
                        @error('anniversary_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Pernikahan atau hari jadi — diingatkan setiap tahun.</div>
                    </div>
                </div>

                <h2 class="panel-title">Pengaturan Pengingat</h2>
                <div class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label for="reminder_days" class="form-label">Kirim email pengingat</label>
                        <select id="reminder_days" name="reminder_days" class="form-select @error('reminder_days') is-invalid @enderror">
                            @foreach($dayOptions as $d)
                                <option value="{{ $d }}" @selected((int) old('reminder_days', $user->reminder_days) === $d)>{{ $d }} hari sebelumnya</option>
                            @endforeach
                        </select>
                        @error('reminder_days') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="reminders_enabled" name="reminders_enabled" value="1"
                                   @checked(old('reminders_enabled', $user->reminders_enabled))>
                            <label class="form-check-label" for="reminders_enabled">Aktifkan email pengingat</label>
                        </div>
                    </div>
                </div>

                <button class="btn btn-plum px-4 mt-4">Simpan Profil</button>
            </form>

            {{-- Momen orang terdekat --}}
            <div class="panel">
                <h2 class="panel-title mb-1">Momen Orang Terdekat</h2>
                <p class="small text-muted mb-3">Ulang tahun ibu, anniversary pasangan, atau tanggal penting lain — diingatkan setiap tahun.</p>

                @forelse($moments as $moment)
                    <div class="mini-card d-flex justify-content-between align-items-center gap-2">
                        <div>
                            <div class="fw-semibold small">{{ $moment->title }}</div>
                            <div class="small text-muted">{{ $types[$moment->type] ?? 'Momen' }} · {{ $dayMonth($moment->date) }}</div>
                        </div>
                        <form method="POST" action="{{ route('profile.moments.destroy', $moment) }}"
                              onsubmit="return confirm('Hapus momen {{ e($moment->title) }}?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-link text-danger">Hapus</button>
                        </form>
                    </div>
                @empty
                    <p class="small text-muted">Belum ada momen orang terdekat.</p>
                @endforelse

                @if($moments->count() < $maxMoments)
                    <form method="POST" action="{{ route('profile.moments.store') }}" class="mt-3 pt-3 border-top" novalidate>
                        @csrf
                        <div class="row g-2">
                            <div class="col-md-5">
                                <label for="moment_title" class="form-label">Nama momen</label>
                                <input id="moment_title" name="title" value="{{ old('title') }}" maxlength="100" placeholder="Ulang Tahun Ibu"
                                       class="form-control @error('title', 'moment') is-invalid @enderror">
                                @error('title', 'moment') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-6 col-md-3">
                                <label for="moment_type" class="form-label">Jenis</label>
                                <select id="moment_type" name="type" class="form-select">
                                    @foreach($types as $value => $label)
                                        <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-4">
                                <label for="moment_date" class="form-label">Tanggal</label>
                                <input id="moment_date" type="date" name="date" value="{{ old('date') }}"
                                       class="form-control @error('date', 'moment') is-invalid @enderror">
                                @error('date', 'moment') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <button class="btn btn-outline-plum w-100 mt-3">+ Tambah Reminder Baru</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Kalender pengingat --}}
        <div class="col-lg-5">
            <div class="panel">
                <h2 class="panel-title mb-1">Smart Calendar & Reminder</h2>
                <p class="small text-muted mb-3">
                    @if($user->reminders_enabled)
                        Email dikirim {{ $user->reminder_days }} hari sebelum setiap momen.
                    @else
                        <span class="text-danger">Email pengingat sedang nonaktif.</span>
                    @endif
                </p>

                @forelse($upcoming as $m)
                    <div class="mini-card">
                        <div class="d-flex justify-content-between">
                            <div class="fw-semibold small">{{ Reminder::TYPE_LABELS[$m['type']] ?? 'Momen' }}: {{ $m['title'] }}</div>
                            <span class="badge-soft primary">{{ $m['days_left'] === 0 ? 'Hari ini' : $m['days_left'] . ' hari lagi' }}</span>
                        </div>
                        <div class="small text-muted mt-1">Tanggal: {{ $fmt($m['occasion_date']) }}</div>
                        @if($user->reminders_enabled)
                            <div class="small text-mauve">
                                Email pengingat: {{ $m['remind_at']->isPast() && ! $m['remind_at']->isToday() ? 'segera dikirim' : $fmt($m['remind_at']) }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-center text-muted small py-4">Isi tanggal ulang tahun, anniversary, atau tambah momen orang terdekat untuk mulai menerima pengingat.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
