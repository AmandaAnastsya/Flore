@csrf
<div class="mb-3">
    <label for="name" class="form-label">Nama Kategori</label>
    <input id="name" type="text" name="name" value="{{ old('name', $category->name ?? '') }}"
           class="form-control @if($errors->has('name') || $errors->has('slug')) is-invalid @endif" required>
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="mb-3">
    <label for="description" class="form-label">Deskripsi</label>
    <textarea id="description" name="description" rows="3"
              class="form-control @error('description') is-invalid @enderror">{{ old('description', $category->description ?? '') }}</textarea>
    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
<div class="form-check mb-3">
    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
           @checked(old('is_active', $category->is_active ?? true))>
    <label class="form-check-label" for="is_active">Aktif (tampil di katalog)</label>
</div>
<button class="btn btn-primary">Simpan</button>
<a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary">Batal</a>