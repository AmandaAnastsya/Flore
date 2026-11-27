@extends('layouts.admin')
@section('title', 'Studio Settings')

@section('content')
@php use App\Services\StudioSettings; @endphp

<div class="admin-head">
    <h1 class="h3 mb-1">Studio Settings</h1>
    <p>Pengaturan toko yang langsung berlaku tanpa mengubah file <code>.env</code>.</p>
</div>

@include('catalog._flash')

<form method="POST" action="{{ route('admin.settings.update') }}" style="max-width: 820px">
    @csrf @method('PUT')

    @foreach(StudioSettings::FIELDS as $group => $fields)
        <div class="panel mb-3">
            <h2 class="panel-title">{{ $group }}</h2>
            <div class="row g-3">
                @foreach($fields as $key => [$label, $type, $rules, $hint])
                    @php $field = str_replace('.', '__', $key); @endphp
                    <div class="{{ in_array($type, ['number', 'time'], true) ? 'col-md-6' : 'col-12' }}">
                        <label for="{{ $field }}" class="form-label small mb-1">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field, $values[$key]) }}"
                               class="form-control @error($field) is-invalid @enderror" @if(in_array('required', $rules, true)) required @endif>
                        @error($field) <div class="invalid-feedback">{{ $message }}</div>
                        @else @if($hint) <div class="form-text">{{ $hint }}</div> @endif
                        @enderror
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-plum">Simpan Pengaturan</button>
        <span class="small text-muted">Perubahan jam reminder berlaku pada jadwal Task Scheduler berikutnya.</span>
    </div>
</form>
@endsection
