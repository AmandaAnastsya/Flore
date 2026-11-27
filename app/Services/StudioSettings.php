<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Studio Settings (sidebar mockup hal. 44).
 * Setiap kunci = path di config/flore.php. Nilai dari tabel `settings` menimpa default .env/config
 * saat aplikasi boot, sehingga kode lain cukup membaca config('flore....').
 */
class StudioSettings
{
    private const CACHE_KEY = 'flore.settings';

    /** grup => [kunci => [label, tipe, aturan validasi, petunjuk]] */
    public const FIELDS = [
        'Profil Toko' => [
            'store.name' => ['Nama toko', 'text', ['required', 'string', 'max:60'], null],
            'store.phone' => ['WhatsApp customer service', 'text', ['nullable', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'], 'Tampil di footer toko, contoh 081234567890'],
            'store.email' => ['Email customer service', 'email', ['nullable', 'email', 'max:100'], null],
            'studio.address' => ['Alamat studio (titik berangkat kurir)', 'text', ['required', 'string', 'max:200'], 'Dipakai untuk tautan rute Google Maps di halaman Logistik'],
        ],
        'Pembayaran (Transfer Bank)' => [
            'payment.bank' => ['Nama bank', 'text', ['required', 'string', 'max:40'], null],
            'payment.account_number' => ['Nomor rekening', 'text', ['required', 'regex:/^[0-9 \-]{5,30}$/'], null],
            'payment.account_name' => ['Atas nama', 'text', ['required', 'string', 'max:80'], null],
            'payment.timeout_hours' => ['Batas waktu pembayaran (jam)', 'number', ['required', 'integer', 'min:1', 'max:168'], 'Pesanan yang belum dibayar setelah batas ini dibatalkan otomatis dan stoknya dikembalikan'],
        ],
        'Pengiriman' => [
            'delivery.lead_hours' => ['Jeda minimal sebelum slot (jam)', 'number', ['required', 'integer', 'min:1', 'max:12'], 'Pesanan hari ini harus dibuat sekian jam sebelum slot dimulai'],
            'delivery.max_days' => ['Pesan maksimal berapa hari ke depan', 'number', ['required', 'integer', 'min:1', 'max:90'], null],
        ],
        'Smart Reminder' => [
            'reminders.time' => ['Jam kirim email pengingat harian', 'time', ['required', 'date_format:H:i'], 'Dijalankan Laravel Task Scheduler setiap hari'],
        ],
        'Inventaris Bunga' => [
            'inventory.shelf_life_days' => ['Umur segar bawaan bunga (hari)', 'number', ['required', 'integer', 'min:1', 'max:30'], 'Untuk bunga yang belum punya umur segar sendiri'],
        ],
    ];

    /** @return array<string,array> kunci => definisi */
    public static function flat(): array
    {
        return array_merge(...array_values(self::FIELDS));
    }

    /** Nilai efektif saat ini (setting tersimpan > config/.env). */
    public function all(): array
    {
        return collect(self::flat())->mapWithKeys(fn ($def, $key) => [$key => config("flore.{$key}")])->all();
    }

    public function save(array $values): void
    {
        DB::transaction(function () use ($values) {
            foreach (array_keys(self::flat()) as $key) {
                if (array_key_exists($key, $values)) {
                    Setting::updateOrCreate(['key' => $key], ['value' => $values[$key] === null ? null : (string) $values[$key]]);
                }
            }
        });

        Cache::forget(self::CACHE_KEY);
        self::applyToConfig();
    }

    /** Dipanggil di AppServiceProvider::boot(). Aman bila tabel belum ada (instalasi baru / sebelum migrate). */
    public static function applyToConfig(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::pluck('value', 'key')->all());
        } catch (Throwable) {
            return;   // database belum siap: tetap pakai default config
        }

        foreach ($stored as $key => $value) {
            if (! array_key_exists($key, self::flat())) {
                continue;
            }
            $type = self::flat()[$key][1];
            config(["flore.{$key}" => $type === 'number' && $value !== null ? (int) $value : $value]);
        }
    }
}
