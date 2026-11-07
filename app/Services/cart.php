<?php

namespace App\Services;

use App\Models\Bouquet;
use App\Models\Flower;
use Illuminate\Support\Collection;

class Cart
{
    public const MAX_QTY = 20;     // batas per bouquet
    public const MAX_STEMS = 200;  // batas tangkai per jenis bunga

    /** @return array<int,int> [bouquet_id => jumlah] */
    private function raw(): array
    {
        return collect(session('cart', []))
            ->filter(fn ($qty, $id) => is_int($id) && is_int($qty) && $qty > 0)
            ->all();
    }

    /** @return array<int,int> [flower_id => jumlah tangkai] */
    private function rawStems(): array
    {
        return collect(session('cart_stems', []))
            ->filter(fn ($qty, $id) => is_int($id) && is_int($qty) && $qty > 0)
            ->all();
    }

    public function quantityOf(int $bouquetId): int
    {
        return $this->raw()[$bouquetId] ?? 0;
    }

    /** Total semua barang (bouquet + tangkai) untuk badge navbar, tanpa query database. */
    public function count(): int
    {
        return array_sum($this->raw()) + array_sum($this->rawStems());
    }

    // ---------------------------------------------------------------- bouquet

    /** Tambah bouquet. Mengembalikan pesan error, atau null kalau berhasil. */
    public function add(Bouquet $bouquet, int $qty): ?string
    {
        if ($bouquet->stock <= 0) {
            return "{$bouquet->name} sedang habis.";
        }

        $current = $this->quantityOf($bouquet->id);
        $wanted = $current + $qty;

        if ($wanted > $bouquet->stock) {
            $left = $bouquet->stock - $current;

            return $left <= 0
                ? "Semua stok {$bouquet->name} ({$bouquet->stock}) sudah ada di keranjangmu."
                : "Stok {$bouquet->name} hanya tersisa {$bouquet->stock}. Kamu sudah punya {$current} di keranjang, jadi hanya bisa menambah {$left} lagi.";
        }

        if ($wanted > self::MAX_QTY) {
            return 'Maksimal ' . self::MAX_QTY . " per bouquet. Kamu sudah punya {$current} di keranjang.";
        }

        $cart = $this->raw();
        $cart[$bouquet->id] = $wanted;
        session()->put('cart', $cart);

        return null;
    }

    /** Ubah jumlah bouquet (nilai akhir). */
    public function set(Bouquet $bouquet, int $qty): ?string
    {
        $cart = $this->raw();

        if (! isset($cart[$bouquet->id])) {
            return "{$bouquet->name} tidak ada di keranjang.";
        }
        if ($bouquet->stock <= 0) {
            return "{$bouquet->name} sedang habis.";
        }
        if ($qty > $bouquet->stock) {
            return "Stok {$bouquet->name} hanya tersisa {$bouquet->stock}.";
        }
        if ($qty > self::MAX_QTY) {
            return 'Maksimal ' . self::MAX_QTY . ' per bouquet.';
        }

        $cart[$bouquet->id] = $qty;
        session()->put('cart', $cart);

        return null;
    }

    public function remove(int $bouquetId): void
    {
        $cart = $this->raw();
        unset($cart[$bouquetId]);
        session()->put('cart', $cart);
    }

    // ----------------------------------------------------------- bunga tangkai

    public function addStem(Flower $flower, int $stems): ?string
    {
        if ($flower->stock <= 0) {
            return "{$flower->name} sedang habis.";
        }

        $current = $this->rawStems()[$flower->id] ?? 0;
        $wanted = $current + $stems;

        if ($wanted > $flower->stock) {
            $left = $flower->stock - $current;

            return $left <= 0
                ? "Semua stok {$flower->name} ({$flower->stock} tangkai) sudah ada di keranjangmu."
                : "Stok {$flower->name} hanya tersisa {$flower->stock} tangkai. Kamu sudah punya {$current} di keranjang, jadi hanya bisa menambah {$left} lagi.";
        }

        if ($wanted > self::MAX_STEMS) {
            return 'Maksimal ' . self::MAX_STEMS . " tangkai per jenis bunga. Kamu sudah punya {$current} di keranjang.";
        }

        $cart = $this->rawStems();
        $cart[$flower->id] = $wanted;
        session()->put('cart_stems', $cart);

        return null;
    }

    public function setStem(Flower $flower, int $stems): ?string
    {
        $cart = $this->rawStems();

        if (! isset($cart[$flower->id])) {
            return "{$flower->name} tidak ada di keranjang.";
        }
        if ($flower->stock <= 0) {
            return "{$flower->name} sedang habis.";
        }
        if ($stems > $flower->stock) {
            return "Stok {$flower->name} hanya tersisa {$flower->stock} tangkai.";
        }
        if ($stems > self::MAX_STEMS) {
            return 'Maksimal ' . self::MAX_STEMS . ' tangkai per jenis bunga.';
        }

        $cart[$flower->id] = $stems;
        session()->put('cart_stems', $cart);

        return null;
    }

    public function removeStem(int $flowerId): void
    {
        $cart = $this->rawStems();
        unset($cart[$flowerId]);
        session()->put('cart_stems', $cart);
    }

    // ------------------------------------------------------------------ umum

    public function clear(): void
    {
        session()->forget(['cart', 'cart_stems']);
    }

    /**
     * Cocokkan isi keranjang dengan kondisi terbaru: buang yang hilang/nonaktif/habis,
     * sesuaikan jumlah dengan stok. Mengembalikan daftar pesan untuk pembeli.
     *
     * @return string[]
     */
    public function sync(): array
    {
        return array_merge($this->syncBouquets(), $this->syncStems());
    }

    private function syncBouquets(): array
    {
        $raw = $this->raw();
        if (! $raw) {
            return [];
        }

        $bouquets = Bouquet::visible()->whereIn('id', array_keys($raw))->get()->keyBy('id');
        $messages = [];
        $clean = [];

        foreach ($raw as $id => $qty) {
            $bouquet = $bouquets->get($id);

            if (! $bouquet) {
                $messages[] = 'Satu bouquet tidak lagi tersedia dan dihapus dari keranjang.';
                continue;
            }
            if ($bouquet->stock <= 0) {
                $messages[] = "{$bouquet->name} sedang habis dan dihapus dari keranjang.";
                continue;
            }
            if ($qty > $bouquet->stock) {
                $messages[] = "Jumlah {$bouquet->name} disesuaikan menjadi {$bouquet->stock} karena stok berkurang.";
                $qty = $bouquet->stock;
            }

            $clean[$id] = $qty;
        }

        if ($clean !== $raw) {
            session()->put('cart', $clean);
        }

        return $messages;
    }

    private function syncStems(): array
    {
        $raw = $this->rawStems();
        if (! $raw) {
            return [];
        }

        $flowers = Flower::active()->whereIn('id', array_keys($raw))->get()->keyBy('id');
        $messages = [];
        $clean = [];

        foreach ($raw as $id => $stems) {
            $flower = $flowers->get($id);

            if (! $flower) {
                $messages[] = 'Satu jenis bunga tidak lagi tersedia dan dihapus dari keranjang.';
                continue;
            }
            if ($flower->stock <= 0) {
                $messages[] = "{$flower->name} sedang habis dan dihapus dari keranjang.";
                continue;
            }
            if ($stems > $flower->stock) {
                $messages[] = "Jumlah {$flower->name} disesuaikan menjadi {$flower->stock} tangkai karena stok berkurang.";
                $stems = $flower->stock;
            }

            $clean[$id] = $stems;
        }

        if ($clean !== $raw) {
            session()->put('cart_stems', $clean);
        }

        return $messages;
    }

    /** Isi keranjang bouquet, dengan harga dari database (bukan dari session). */
    public function lines(): Collection
    {
        $raw = $this->raw();
        if (! $raw) {
            return collect();
        }

        $bouquets = Bouquet::visible()->with('category')->whereIn('id', array_keys($raw))->get()->keyBy('id');

        return collect($raw)->map(function ($qty, $id) use ($bouquets) {
            $bouquet = $bouquets->get($id);

            return $bouquet ? [
                'bouquet' => $bouquet,
                'quantity' => $qty,
                'subtotal' => round((float) $bouquet->price * $qty, 2),
            ] : null;
        })->filter()->values();
    }

    /** Isi keranjang bunga per tangkai; subtotal memakai harga paket otomatis. */
    public function stemLines(): Collection
    {
        $raw = $this->rawStems();
        if (! $raw) {
            return collect();
        }

        $flowers = Flower::active()->whereIn('id', array_keys($raw))->get()->keyBy('id');

        return collect($raw)->map(function ($stems, $id) use ($flowers) {
            $flower = $flowers->get($id);

            return $flower ? [
                'flower' => $flower,
                'quantity' => $stems,
                'subtotal' => $flower->priceFor($stems),
            ] : null;
        })->filter()->values();
    }
}