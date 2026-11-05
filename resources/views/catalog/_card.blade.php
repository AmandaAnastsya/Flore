<div class="product">
    <a href="{{ route('catalog.show', $bouquet) }}" class="card-link position-relative d-block">
        <img src="{{ $bouquet->image_url }}" alt="{{ $bouquet->name }}" loading="lazy">
        <div class="tags">
            @if($bouquet->stock_status === 'out') <span class="tag tag-out">Habis</span> @endif
            @if($bouquet->is_same_day) <span class="tag tag-sameday">Same Day</span> @endif
            @if($bouquet->style === 'luxury') <span class="tag tag-luxury">Luxury</span> @endif
        </div>
    </a>
    <div class="p-3 d-flex flex-column flex-grow-1">
        <a href="{{ route('catalog.show', $bouquet) }}" class="card-link">
            <div class="fw-semibold">{{ $bouquet->name }}</div>
        </a>
        <div class="text-muted small mb-2">{{ $bouquet->description }}</div>
        <div class="price mb-1">Rp {{ number_format($bouquet->price, 0, ',', '.') }}</div>
        <div class="small mb-3">
            @if($bouquet->stock_status === 'low')
                <span class="text-warning-emphasis">Sisa {{ $bouquet->stock }}</span>
            @elseif($bouquet->stock_status === 'ok' && $bouquet->is_same_day)
                <span class="text-success">Same day delivery tersedia</span>
            @endif
        </div>
        <div class="mt-auto d-grid gap-2">
            <a href="{{ route('catalog.show', $bouquet) }}" class="btn btn-outline-secondary btn-sm">Lihat detail</a>
            @if($bouquet->stock_status === 'out')
                <button class="btn btn-outline-secondary" disabled>Stok habis</button>
            @else
                <form method="POST" action="{{ route('cart.store', $bouquet) }}" class="d-grid">
    @csrf
    <button class="btn btn-rose">Add to cart</button>
</form>
            @endif
        </div>
    </div>
</div>