<?php

namespace Database\Seeders;

use App\Models\Bouquet;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\WizardQuery;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Data DEMO untuk presentasi (dashboard, laporan, reminder, testimonial tidak kosong).
 * TIDAK dijalankan otomatis — jalankan manual:  php artisan db:seed --class=DemoSeeder
 *
 * - Semua tanggal relatif terhadap hari ini, jadi dashboard selalu terlihat "hidup".
 * - Aman dijalankan ulang: data demo lama (akun @demo.flore.test) dihapus lalu dibuat ulang.
 * - Stok katalog tidak diubah. Ditolak di environment production.
 */
class DemoSeeder extends Seeder
{
    public const DOMAIN = 'demo.flore.test';
    public const PASSWORD = 'demo12345';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder tidak boleh dijalankan di production.');
        }

        if (Bouquet::count() === 0) {
            throw new RuntimeException('Katalog kosong. Jalankan php artisan db:seed terlebih dahulu.');
        }

        DB::transaction(function () {
            $this->clearPreviousDemo();
            $customers = $this->customers();
            $couriers = $this->couriers();
            $orders = $this->orders($customers, $couriers);
            $this->testimonials($orders);
            $this->wizardQueries($customers);
            $this->inventory();
            $this->production();
            $this->expenses();
        });

        $this->command?->info('Data demo dibuat. Login customer demo: clarissa@' . self::DOMAIN . ' / ' . self::PASSWORD);
    }

    /** Nomor HP kurir demo — dipakai untuk mengenali & menghapusnya saat seeder dijalankan ulang. */
    public const COURIERS = [
        ['Doni Pratama',     '081277120001', 'motor',  'B 4321 KZT', 'Area Jakarta Selatan & Pusat'],
        ['Rian Hidayat',     '081277120002', 'mobil',  'B 1987 PFR', 'Pesanan besar & wedding'],
        ['Bagas Kurniawan',  '081277120003', 'motor',  'B 5560 TQA', null],
        ['Kurir Instan',     '081277120004', 'instan', null,         'Cadangan saat kurir toko penuh'],
    ];

    private function clearPreviousDemo(): void
    {
        Courier::whereIn('phone', array_column(self::COURIERS, 1))->delete();   // pesanan lain: courier_id → null

        $ids = User::where('email', 'like', '%@' . self::DOMAIN)->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        $proofs = Order::whereIn('user_id', $ids)->whereNotNull('payment_proof')->pluck('payment_proof');
        WizardQuery::whereIn('user_id', $ids)->delete();
        Order::whereIn('user_id', $ids)->each(fn (Order $o) => $o->delete());   // item & testimonial ikut terhapus (cascade)
        User::whereIn('id', $ids)->delete();                                     // momen & reminder ikut terhapus (cascade)
        $proofs->each(fn ($p) => Storage::disk('local')->delete($p));
    }

    /** @return array<string, User> */
    private function customers(): array
    {
        $today = today();
        $people = [
            // kunci, nama, no HP, ulang tahun (relatif), anniversary (relatif)
            ['clarissa', 'Clarissa Valery',  '081234560001', $today->copy()->addDays(6)->subYears(27),  $today->copy()->addDays(20)->subYears(3)],
            ['julian',   'Julian Santoso',   '081234560002', $today->copy()->addMonths(4)->subYears(30), $today->copy()->addDays(12)->subYears(5)],
            ['ananya',   'Ananya Rahma',     '081234560003', $today->copy()->addDays(25)->subYears(24), null],
            ['budi',     'Budi Pratama',     '081234560004', $today->copy()->subMonths(2)->subYears(35), $today->copy()->addMonths(3)->subYears(8)],
            ['devi',     'Devi Maharani',    '081234560005', $today->copy()->addMonths(6)->subYears(29), null],
            ['nadia',    'Nadia Putri',      '081234560006', null, null],
        ];

        $users = [];
        foreach ($people as [$key, $name, $phone, $dob, $anniv]) {
            $users[$key] = User::create([
                'name' => $name, 'email' => "{$key}@" . self::DOMAIN, 'phone' => $phone,
                'date_of_birth' => $dob?->toDateString(), 'anniversary_date' => $anniv?->toDateString(),
                'role' => 'customer', 'password' => self::PASSWORD,
            ]);
            $users[$key]->forceFill(['created_at' => $today->copy()->subDays(rand(30, 60))])->save();
        }

        $users['clarissa']->specialMoments()->create([
            'type' => 'birthday', 'title' => 'Ulang Tahun Ibu', 'date' => $today->copy()->addDays(10)->subYears(55)->toDateString(),
        ]);
        $users['julian']->specialMoments()->create([
            'type' => 'anniversary', 'title' => 'Anniversary Orang Tua', 'date' => $today->copy()->addDays(40)->subYears(30)->toDateString(),
        ]);

        return $users;
    }

    /** @return Courier[] */
    private function couriers(): array
    {
        return array_map(fn ($row) => Courier::create(array_combine(['name', 'phone', 'vehicle', 'plate_number', 'notes'], $row)), self::COURIERS);
    }

    /** @return Order[] pesanan terkirim (untuk testimonial) */
    private function orders(array $c, array $couriers): array
    {
        $b = Bouquet::pluck('id', 'slug');
        $p = Bouquet::pluck('price', 'slug');
        $n = Bouquet::pluck('name', 'slug');
        $cost = Bouquet::pluck('cost_price', 'slug');   // HPP dari Inventaris (boleh kosong)

        // pelanggan, item [slug => qty], status, hari lalu, occasion, kota, penerima, pesan kartu
        $plan = [
            ['clarissa', ['the-sweet-blush-romance' => 1],                         'delivered', 27, 'anniversary',   'Jakarta Selatan', 'Julian Santoso',  'Happy 3rd anniversary, my love!'],
            ['julian',   ['velvet-rose-ribbon-box' => 1],                          'delivered', 24, 'anniversary',   'Jakarta Pusat',   'Clarissa Valery', 'Untuk kamu, selalu.'],
            ['ananya',   ['sunlit-peach-bloom-box' => 1, 'mini-daisy-delight' => 1], 'delivered', 21, 'birthday',      'Bandung',         'Rina Kartika',    'Selamat ulang tahun, Rin!'],
            ['budi',     ['garden-basket-bliss' => 2],                             'delivered', 18, 'grand-opening', 'Tangerang',       'Kedai Kopi Senja', 'Sukses selalu untuk usaha barunya.'],
            ['devi',     ['blush-satin-hand-bouquet' => 1],                        'delivered', 15, 'graduation',    'Depok',           'Sarah Amelia',    'Congratulations, Sarjana!'],
            ['clarissa', ['petite-peony-posy' => 1],                               'delivered', 12, 'birthday',      'Jakarta Selatan', 'Maya Lestari',    'Happy birthday, Maya!'],
            ['nadia',    ['eternal-white-wedding-bouquet' => 1],                   'delivered', 9,  'wedding',       'Bogor',           'Nadia Putri',     null],
            ['julian',   ['the-sweet-blush-romance' => 1],                         'delivered', 6,  'anniversary',   'Jakarta Pusat',   'Clarissa Valery', 'I love you.'],
            ['ananya',   ['mini-daisy-delight' => 2],                              'delivered', 3,  'apology',       'Bandung',         'Dimas Prasetyo',  'Maaf ya, aku salah.'],
            ['devi',     ['soft-lavender-whispers' => 1],                          'out_for_delivery', 1, 'birthday', 'Depok',          'Ibu Ratna',       'Selamat ulang tahun, Ibu tersayang.'],
            ['budi',     ['rustic-blush-bridal-bouquet' => 1],                     'preparing', 1,  'wedding',       'Tangerang',       'Laras & Bima',    'Happy wedding!'],
            ['clarissa', ['graceful-pure-cashmere' => 1],                          'payment_confirmed', 0, 'anniversary', 'Jakarta Selatan', 'Julian Santoso', null],
            ['nadia',    ['pastel-dream-basket' => 1],                             'proof_uploaded', 0, 'apology',    'Bogor',           'Kak Dinda',       'Maaf telat ngucapin.'],
            ['julian',   ['sunlit-peach-bloom-box' => 1],                          'pending_payment', 0, 'birthday',   'Jakarta Pusat',   'Andre Wijaya',    null],
            ['ananya',   ['velvet-rose-ribbon-box' => 1],                          'cancelled', 8,  'anniversary',   'Bandung',         'Dimas Prasetyo',  null],
            // halaman Logistik: satu terkirim hari ini, satu siap kirim tetapi belum ada kurir
            ['julian',   ['petite-peony-posy' => 1],                               'delivered', 1,  'birthday',      'Jakarta Selatan', 'Sinta Maharani',  'Happy birthday, Sin!'],
            ['nadia',    ['mini-daisy-delight' => 1],                              'preparing', 1,  'apology',       'Jakarta Barat',   'Rafi Ananda',     'Maaf ya, Rafi.'],
        ];

        $slots = ['morning', 'afternoon', 'evening'];
        $delivered = [];

        foreach ($plan as $i => [$who, $items, $state, $daysAgo, $occasion, $city, $recipient, $card]) {
            $created = now()->subDays($daysAgo)->setTime(9 + $i % 9, ($i * 7) % 60);
            $subtotal = collect($items)->map(fn ($qty, $slug) => $p[$slug] * $qty)->sum();
            $paid = in_array($state, ['delivered', 'out_for_delivery', 'preparing', 'payment_confirmed'], true);
            $status = $state === 'proof_uploaded' ? 'pending_payment' : $state;

            $order = Order::unguarded(fn () => Order::create([
                'order_number' => 'FLR-' . $created->format('ymd') . '-' . strtoupper(Str::random(5)),
                'user_id' => $c[$who]->id,
                'recipient_name' => $recipient, 'recipient_phone' => '0812' . str_pad((string) (77000000 + $i), 8, '0', STR_PAD_LEFT),
                'delivery_city' => $city, 'delivery_address' => 'Jl. Melati No. ' . (10 + $i) . ', ' . $city,
                // yang sedang diproses studio dikirim hari ini, supaya antrean Logistik terisi
                'delivery_date' => (in_array($status, ['preparing', 'out_for_delivery'], true)
                    ? today() : $created->copy()->addDays($status === 'delivered' ? 1 : 2))->toDateString(),
                'delivery_slot' => $slots[$i % 3], 'occasion' => $occasion, 'card_message' => $card,
                'sender_name' => $c[$who]->name, 'is_anonymous' => false,
                'subtotal' => $subtotal, 'delivery_fee' => 0, 'discount' => 0, 'total_amount' => $subtotal,
                'status' => $status,
                'payment_status' => $paid ? 'confirmed' : ($state === 'proof_uploaded' ? 'proof_uploaded' : 'unpaid'),
                'paid_at' => $paid ? $created->copy()->addHours(2) : null,
                'cancelled_at' => $state === 'cancelled' ? $created->copy()->addHours(5) : null,
                'created_at' => $created, 'updated_at' => $created,
            ]));

            foreach ($items as $slug => $qty) {
                $order->items()->create([
                    'bouquet_id' => $b[$slug], 'bouquet_name' => $n[$slug],
                    'price' => $p[$slug], 'unit_cost' => $cost[$slug], 'quantity' => $qty, 'subtotal' => $p[$slug] * $qty,
                ]);
            }

            if ($state === 'proof_uploaded') {
                $path = 'payment-proofs/demo-' . Str::lower(Str::random(10)) . '.pdf';
                Storage::disk('local')->put($path, "%PDF-1.4\n% Bukti transfer demo Floré\n%%EOF\n");
                $order->update(['payment_proof' => $path]);
            }

            $this->logistics($order, $i, $couriers);

            if ($status === 'delivered') {
                $delivered[] = $order;
            }
        }

        return $delivered;
    }

    /** Data kurir, jam berangkat, ETA & serah terima yang konsisten dengan status pesanan. */
    private function logistics(Order $order, int $i, array $couriers): void
    {
        $courier = $couriers[$i % 3];   // kurir instan hanya cadangan
        $slotStart = $order->delivery_date->copy()->setTime(\App\Services\DeliverySchedule::SLOTS[$order->delivery_slot]['start'], 0);

        $data = match ($order->status) {
            'delivered' => (function () use ($courier, $slotStart, $order) {
                $deliveredAt = $slotStart->copy()->addMinutes(75)->min(now()->subMinutes(20));
                return [
                    'courier_id' => $courier->id, 'travel_minutes' => 45,
                    'dispatched_at' => $deliveredAt->copy()->subMinutes(40), 'eta_at' => $deliveredAt->copy()->addMinutes(5),
                    'delivered_at' => $deliveredAt, 'received_by' => $order->recipient_name,
                ];
            })(),
            'out_for_delivery' => [
                'courier_id' => $couriers[0]->id, 'travel_minutes' => 45,
                'dispatched_at' => now()->subMinutes(25), 'eta_at' => now()->addMinutes(20),
            ],
            // satu "Siap Pick-up" (sudah ada kurir), satu "Menunggu Kurir"
            'preparing' => $order->recipient_name === 'Laras & Bima' ? ['courier_id' => $couriers[1]->id, 'travel_minutes' => 60] : [],
            default => [],
        };

        if ($data) {
            $order->forceFill($data)->save();
        }
    }

    /** Biaya operasional contoh 6 bulan terakhir (ditandai "[Demo]" supaya bisa dibersihkan). */
    private function expenses(): void
    {
        \App\Models\Expense::where('description', 'like', '[Demo]%')->delete();

        foreach (range(5, 0) as $ago) {
            $month = today()->startOfMonth()->subMonthsNoOverflow($ago);
            foreach ([['gaji', 'Gaji 2 florist & 1 admin', 1800000], ['sewa', 'Sewa studio', 900000], ['utilitas', 'Listrik, air & internet', 350000],
                      ['marketing', 'Iklan Instagram', 250000]] as [$cat, $desc, $amount]) {
                $day = $month->copy()->addDays(4);
                if ($day->gt(today())) {
                    continue;
                }
                \App\Models\Expense::create(['spent_on' => $day->toDateString(), 'category' => $cat, 'description' => "[Demo] {$desc}", 'amount' => $amount]);
            }
        }
    }

    public const FLORISTS = [
        ['Maya Sari', '081277330001', 'Wedding & hand bouquet'],
        ['Rina Wulandari', '081277330002', 'Bloom box & flower basket'],
    ];

    /** Florist demo + tahap produksi yang konsisten dengan status pesanan demo. */
    private function production(): void
    {
        \App\Models\Florist::whereIn('phone', array_column(self::FLORISTS, 1))->delete();
        $florists = array_map(fn ($f) => \App\Models\Florist::create(array_combine(['name', 'phone', 'specialty'], $f)), self::FLORISTS);

        $demoOrders = Order::whereHas('user', fn ($u) => $u->where('email', 'like', '%@' . self::DOMAIN))->get();
        foreach ($demoOrders as $i => $order) {
            $data = match (true) {
                in_array($order->status, ['out_for_delivery', 'delivered'], true) => [
                    'florist_id' => $florists[$i % 2]->id, 'fulfillment_stage' => 'ready', 'qc_passed_at' => $order->dispatched_at?->copy()->subMinutes(30),
                ],
                $order->status === 'preparing' => $order->courier_id
                    ? ['florist_id' => $florists[0]->id, 'fulfillment_stage' => 'packing', 'qc_passed_at' => now()->subMinutes(40), 'production_note' => 'Pita satin ivory, kartu emas']
                    : ['florist_id' => $florists[1]->id, 'fulfillment_stage' => 'arranging'],
                default => [],
            };
            if ($data) {
                $order->forceFill($data + ['stage_updated_at' => now()->subMinutes(20)])->save();
            }
        }
    }

    /** Inventory Hub: contoh pita, wrapping & vas (hanya bila belum ada), dan tanggal tiba bunga yang bervariasi. */
    private function inventory(): void
    {
        if (\App\Models\Supply::count() === 0) {
            foreach ([
                ['wrapping', 'Pita Satin 2,5 cm', 'Dusty Pink', 'meter', 480, 100, 2000],
                ['wrapping', 'Pita Organza', 'Ivory', 'meter', 60, 80, 1500],
                ['wrapping', 'Korean Wrapping Paper', 'Blush Matte', 'lembar', 320, 100, 3500],
                ['wrapping', 'Kertas Tisu Bunga', 'Putih', 'lembar', 240, 80, 800],
                ['vase', 'Vas Kaca Silinder', 'Bening 25 cm', 'pcs', 18, 8, 45000],
                ['vase', 'Bloom Box Velvet', 'Merah Marun', 'pcs', 6, 8, 38000],
                ['vase', 'Kartu Ucapan Premium', 'Gold Foil', 'pcs', 150, 50, 2500],
            ] as [$type, $name, $variant, $unit, $stock, $reorder, $cost]) {
                $supply = \App\Models\Supply::create(compact('type', 'name', 'variant', 'unit', 'stock') + ['reorder_level' => $reorder, 'cost_per_unit' => $cost]);
                app(\App\Services\InventoryService::class)->opening($supply);
            }
        }

        // bunga tiba di hari yang berbeda-beda supaya indikator kesegaran terlihat
        \App\Models\Flower::all()->each(fn ($f, $i) => $f->forceFill(['last_restocked_at' => now()->subDays($i % 6)])->save());
    }

    private function testimonials(array $orders): void
    {
        $reviews = [
            [5, 'Rangkaiannya cantik banget, bunganya segar dan wangi. Pacarku langsung terharu!', true],
            [5, 'Pengiriman tepat waktu dan kartu ucapannya rapi. Pasti pesan lagi di Floré.', true],
            [4, 'Bunganya bagus dan sesuai foto. Packing-nya elegan, cocok untuk kado.', true],
            [5, 'Smart Wizard-nya membantu sekali, aku jadi tahu bunga yang pas untuk minta maaf.', false],
        ];

        foreach (array_slice($orders, 0, count($reviews)) as $i => $order) {
            [$rating, $content, $approved] = $reviews[$i];
            $at = Carbon::parse($order->delivery_date)->setTime(19, 0);

            Testimonial::unguarded(fn () => Testimonial::create([
                'user_id' => $order->user_id, 'order_id' => $order->id, 'rating' => $rating, 'content' => $content,
                'is_approved' => $approved, 'approved_at' => $approved ? $at->copy()->addDay() : null,
                'created_at' => $at, 'updated_at' => $at,
            ]));
        }
    }

    private function wizardQueries(array $customers): void
    {
        $occasions = ['anniversary' => 35, 'birthday' => 25, 'wedding' => 15, 'graduation' => 10, 'apology' => 10, 'grand-opening' => 5];
        $colors = ['pink' => 40, 'white' => 25, 'lavender' => 15, 'red' => 12, 'peach' => 8];
        $styles = ['soft-romantic', 'classic', 'modern', 'luxury'];
        $budgets = ['under-200', '200-350', '350-500', '500-750', 'over-750'];
        $users = array_values($customers);
        $engine = app(\App\Services\RecommendationEngine::class);

        for ($i = 0; $i < 48; $i++) {
            $at = now()->subDays(rand(0, 29))->subMinutes(rand(0, 600));
            $answers = array_filter([
                'occasion' => $this->weighted($occasions),
                'color' => rand(1, 10) > 2 ? $this->weighted($colors) : null,
                'style' => rand(1, 10) > 4 ? $styles[array_rand($styles)] : null,
                'budget' => rand(1, 10) > 3 ? $budgets[array_rand($budgets)] : null,
            ]);
            // hasil nyata dari engine → mode, jumlah & bouquet teratas konsisten dengan katalog
            $result = $engine->recommend($answers);

            WizardQuery::unguarded(fn () => WizardQuery::create([
                'user_id' => $users[$i % count($users)]->id,
                ...$result->criteria,
                'budget' => $result->budget,
                'mode' => $result->mode, 'result_count' => $result->total,
                'bouquet_ids' => $result->items->take(3)->map(fn ($r) => $r['bouquet']->id)->values()->all(),
                'created_at' => $at, 'updated_at' => $at,
            ]));
        }
    }

    private function weighted(array $weights): string
    {
        $roll = rand(1, array_sum($weights));
        foreach ($weights as $value => $w) {
            if (($roll -= $w) <= 0) {
                return $value;
            }
        }

        return array_key_first($weights);
    }
}
