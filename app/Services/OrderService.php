<?php

namespace App\Services;

use App\Exceptions\OrderException;
use App\Models\Bouquet;
use App\Models\Flower;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Buat order dan kurangi stok dalam SATU transaksi.
     *
     * @param  array<int,int>  $bouquetQty  [bouquet_id => jumlah]
     * @param  array<int,int>  $stemQty     [flower_id => jumlah tangkai]
     *
     * @throws OrderException
     */
    public function place(User $user, array $checkout, array $bouquetQty, array $stemQty): Order
    {
        if (! $bouquetQty && ! $stemQty) {
            throw new OrderException('Keranjangmu masih kosong.');
        }

        return DB::transaction(function () use ($user, $checkout, $bouquetQty, $stemQty) {
            // 1. Kunci baris stok (urut id agar dua transaksi tidak saling menunggu / deadlock)
            $bouquets = $bouquetQty
                ? Bouquet::visible()->whereIn('bouquets.id', array_keys($bouquetQty))
                    ->orderBy('bouquets.id')->lockForUpdate()->get()->keyBy('id')
                : collect();

            $flowers = $stemQty
                ? Flower::active()->whereIn('flowers.id', array_keys($stemQty))
                    ->orderBy('flowers.id')->lockForUpdate()->get()->keyBy('id')
                : collect();

            // 2. Validasi semua barang SEBELUM mengubah apa pun
            foreach ($bouquetQty as $id => $qty) {
                $b = $bouquets->get($id);

                if (! $b) {
                    throw new OrderException('Satu bouquet tidak lagi tersedia.');
                }
                if ($b->stock < $qty) {
                    throw new OrderException($b->stock <= 0
                        ? "{$b->name} sedang habis."
                        : "Stok {$b->name} hanya tersisa {$b->stock}.");
                }
            }

            foreach ($stemQty as $id => $stems) {
                $f = $flowers->get($id);

                if (! $f) {
                    throw new OrderException('Satu jenis bunga tidak lagi tersedia.');
                }
                if ($f->stock < $stems) {
                    throw new OrderException($f->stock <= 0
                        ? "{$f->name} sedang habis."
                        : "Stok {$f->name} hanya tersisa {$f->stock} tangkai.");
                }
            }

            // 3. Jadwal dicek lagi (bisa basi sejak halaman tinjau dibuka)
            $problem = DeliverySchedule::problem(
                $checkout['delivery_date'],
                $checkout['delivery_slot'],
                $bouquets->map(fn ($b) => ['bouquet' => $b])
            );

            if ($problem) {
                throw new OrderException($problem[1] . ' Silakan pilih jadwal ulang.', 'checkout');
            }

            // 4. Hitung dari harga database
            $subtotal = 0.0;
            foreach ($bouquetQty as $id => $qty) {
                $subtotal += (float) $bouquets[$id]->price * $qty;
            }
            foreach ($stemQty as $id => $stems) {
                $subtotal += $flowers[$id]->priceFor($stems);
            }
            $subtotal = round($subtotal, 2);

            // 5. Order
            $order = Order::create([
                'user_id' => $user->id,
                'recipient_name' => $checkout['recipient_name'],
                'recipient_phone' => $checkout['recipient_phone'],
                'delivery_city' => $checkout['delivery_city'],
                'delivery_address' => $checkout['delivery_address'],
                'delivery_date' => $checkout['delivery_date'],
                'delivery_slot' => $checkout['delivery_slot'],
                'occasion' => $checkout['occasion'] ?? null,
                'card_message' => $checkout['card_message'] ?? null,
                'sender_name' => $checkout['sender_name'] ?? null,
                'is_anonymous' => $checkout['is_anonymous'] ?? false,
                'subtotal' => $subtotal,
                'delivery_fee' => 0,
                'discount' => 0,
                'total_amount' => $subtotal,
                'status' => 'pending_payment',
                'payment_status' => 'unpaid',
            ]);

            // 6. Item + kurangi stok. WHERE stock >= jumlah membuat stok tak pernah negatif.
            foreach ($bouquetQty as $id => $qty) {
                $b = $bouquets[$id];

                if (Bouquet::whereKey($id)->where('stock', '>=', $qty)->decrement('stock', $qty) === 0) {
                    throw new OrderException("Stok {$b->name} baru saja habis.");
                }

                $order->items()->create([
                    'bouquet_id' => $b->id,
                    'bouquet_name' => $b->name,
                    'price' => $b->price,
                    'quantity' => $qty,
                    'subtotal' => round((float) $b->price * $qty, 2),
                ]);
            }

            foreach ($stemQty as $id => $stems) {
                $f = $flowers[$id];

                if (Flower::whereKey($id)->where('stock', '>=', $stems)->decrement('stock', $stems) === 0) {
                    throw new OrderException("Stok {$f->name} baru saja habis.");
                }

                $order->items()->create([
                    'flower_id' => $f->id,
                    'flower_name' => $f->name,
                    'price' => $f->price_per_stem,
                    'quantity' => $stems,
                    'subtotal' => $f->priceFor($stems),   // sudah memakai harga paket
                ]);
            }

            return $order;
        });
    }
}