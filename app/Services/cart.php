<?php

namespace App\Services;

use App\Models\Bouquet;
use Illuminate\Support\Collection;

class Cart
{
    public const MAX_QTY = 20;   // batas per bouquet per keranjang

    /** @return array<int,int> [bouquet_id => jumlah] */
    private function raw(): array
    {
        return collect(session('cart', []))
            ->filter(fn ($qty, $id) => is_int($id) && is_int($qty) && $qty > 0)
            ->all();
    }

    public function quantityOf(int $bouquetId): int
    {
        return $this->raw()[$bouquetId] ?? 0;
    }

    /** Total jumlah barang (untuk badge navbar), tanpa query database. */
    public function count(): int
    {
        return array_sum($this->raw());
    }

    /** Tambah ke keranjang. Mengembalikan pesan error, atau null kalau berhasil. */
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

    /**
     * Cocokkan isi keranjang dengan kondisi terbaru:
     * hapus bouquet yang hilang/nonaktif/habis, dan sesuaikan jumlah dengan stok.
     * Mengembalikan daftar pesan untuk ditampilkan ke pembeli.
     *
     * @return string[]
     */
    public function sync(): array
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

    /** Isi keranjang dengan harga dari database (bukan dari session). */
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
}