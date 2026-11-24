<?php

namespace Database\Seeders;

use App\Models\Flower;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Bunga per tangkai final. Aman dijalankan ulang: data & harga disinkronkan,
 * STOK hanya diisi saat bunga pertama kali dibuat (stok berjalan tidak ditimpa).
 */
class FlowerSeeder extends Seeder
{
    /** nama, spesies, warna, asal, harga/tangkai, tangkai per paket, harga paket, stok awal */
    public const CATALOG = [
        ['Rose Merah',      'rose',          'red',    'Lokal (Bandung)',   8000, 10,  70000, 200],
        ['Rose Pink',       'rose',          'pink',   'Lokal (Bandung)',   7000, 10,  60000, 180],
        ['Rose Putih',      'rose',          'white',  'Lokal (Bandung)',   7000, 10,  60000, 150],
        ['Lily',            'lily',          'white',  'Import (Holland)', 25000,  5, 110000,  60],
        ['Matahari',        'sunflower',     'yellow', 'Lokal (Malang)',   15000,  5,  65000,  80],
        ['Sedap Malam',     'tuberose',      'white',  'Lokal (Malang)',    6000, 10,  50000, 120],
        ['Baby Rose Spray', 'rose',          'pink',   'Lokal (Bandung)',  12000,  5,  50000,  90],
        ['Gerbera',         'gerbera',       'mixed',  'Lokal (Lembang)',   9000, 10,  80000, 110],
        ['Tulip',           'tulip',         'pink',   'Import (Holland)', 22000,  5, 100000,  60],
        ["Baby's Breath",   'gypsophila',    'white',  'Lokal (Lembang)',  20000,  5,  90000,  70],
        ['Anyelir',         'carnation',     'pink',   'Lokal (Lembang)',   7000, 10,  60000, 130],
        ['Krisan',          'chrysanthemum', 'white',  'Lokal (Cianjur)',   8000, 10,  70000, 140],
        ['Hydrangea',       'hydrangea',     'blue',   'Import (Holland)', 30000,  3,  80000,  45],
        ['Eustoma',         'eustoma',       'purple', 'Import (Ecuador)', 15000,  5,  65000,  60],
        ['Peony',           'peony',         'pink',   'Import (Holland)', 55000, null,  null,  40],
        ['Daisy',           'daisy',         'white',  'Lokal (Lembang)',   6000, 10,  50000, 160],
    ];

    public const REORDER_LEVEL = 30;

    public function run(): void
    {
        foreach (self::CATALOG as [$name, $species, $color, $origin, $price, $bundleSize, $bundlePrice, $stock]) {
            $flower = Flower::withTrashed()->firstOrNew(['slug' => Str::slug($name)]);

            $flower->fill([
                'name' => $name, 'species' => $species, 'color' => $color, 'origin' => $origin,
                'price_per_stem' => $price, 'bundle_size' => $bundleSize, 'bundle_price' => $bundlePrice,
                'is_active' => true,
            ]);

            if (! $flower->exists) {
                $flower->stock = $stock;
                $flower->reorder_level = self::REORDER_LEVEL;
            }

            $flower->save();
        }
    }
}
