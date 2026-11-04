@csrf
<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Nama Bouquet</label>
        <input id="name" name="name" value="{{ old('name', $bouquet->name) }}"
               class="form-control @error('name') is-invalid @enderror" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="category_id" class="form-label">Kategori</label>
        <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
            <option value="">-- Pilih --</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id', $bouquet->category_id) == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        @error('category_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Deskripsi / Isi Bunga</label>
        <textarea id="description" name="description" rows="2"
                  class="form-control @error('description') is-invalid @enderror">{{ old('description', $bouquet->description) }}</textarea>
        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-4">
        <label for="price" class="form-label">Harga (Rp)</label>
        <input id="price" type="number" min="0" step="1" name="price" value="{{ old('price', $bouquet->price ? (int) $bouquet->price : '') }}"
               class="form-control @error('price') is-invalid @enderror" required>
        @error('price') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label for="stock" class="form-label">Stok</label>
        <input id="stock" type="number" min="0" name="stock" value="{{ old('stock', $bouquet->stock) }}"
               class="form-control @error('stock') is-invalid @enderror" required>
        @error('stock') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label for="low_stock_threshold" class="form-label">Batas stok menipis</label>
        <input id="low_stock_threshold" type="number" min="0" name="low_stock_threshold"
               value="{{ old('low_stock_threshold', $bouquet->low_stock_threshold) }}"
               class="form-control @error('low_stock_threshold') is-invalid @enderror" required>
        @error('low_stock_threshold') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    @php
        $fields = [
            'occasion' => ['Occasion', \App\Models\Bouquet::OCCASIONS, true],
            'color' => ['Warna', \App\Models\Bouquet::COLORS, true],
            'style' => ['Style', \App\Models\Bouquet::STYLES, true],
            'size' => ['Ukuran', \App\Models\Bouquet::SIZES, false],
        ];
    @endphp
    @foreach($fields as $field => [$label, $options, $nullable])
        <div class="col-md-3">
            <label for="{{ $field }}" class="form-label">{{ $label }}</label>
            <select id="{{ $field }}" name="{{ $field }}" class="form-select @error($field) is-invalid @enderror">
                @if($nullable) <option value="">-- Tidak ditentukan --</option> @endif
                @foreach($options as $option)
                    <option value="{{ $option }}" @selected(old($field, $bouquet->$field) === $option)>
                        {{ ucfirst(str_replace('-', ' ', $option)) }}
                    </option>
                @endforeach
            </select>
            @error($field) <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    @endforeach

    <div class="col-12">
        <label for="image" class="form-label">Gambar (JPG/PNG/WEBP, maks 2 MB)</label>
        @if($bouquet->image)
            <div class="mb-2"><img src="{{ $bouquet->image_url }}" alt="" style="height: 90px" class="rounded"></div>
        @endif
        <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp"
               class="form-control @error('image') is-invalid @enderror">
        @error('image') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <input type="hidden" name="is_same_day" value="0">
        <div class="form-check form-check-inline">
            <input type="checkbox" class="form-check-input" id="is_same_day" name="is_same_day" value="1"
                   @checked(old('is_same_day', $bouquet->is_same_day))>
            <label class="form-check-label" for="is_same_day">Same-day delivery</label>
        </div>
        <input type="hidden" name="is_active" value="0">
        <div class="form-check form-check-inline">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                   @checked(old('is_active', $bouquet->is_active))>
            <label class="form-check-label" for="is_active">Aktif (tampil di katalog)</label>
        </div>
    </div>
</div>

<div class="mt-4">
    <button class="btn btn-primary">Simpan</button>
    <a href="{{ route('admin.bouquets.index') }}" class="btn btn-outline-secondary">Batal</a>
</div>