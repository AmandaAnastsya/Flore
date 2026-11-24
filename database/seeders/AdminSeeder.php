<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Akun admin awal dari .env (ADMIN_EMAIL, ADMIN_PASSWORD, ADMIN_NAME, ADMIN_PHONE).
 * - Password tidak pernah ditulis di kode.
 * - Production: ADMIN_PASSWORD wajib diisi (min. 12 karakter).
 * - Lokal tanpa ADMIN_PASSWORD: admin baru diberi password acak yang ditampilkan sekali;
 *   admin yang sudah ada tidak diubah passwordnya.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $cfg = config('flore.admin');
        $password = $cfg['password'] ?: null;

        if (app()->isProduction() && (! $password || strlen($password) < 12)) {
            throw new RuntimeException('Isi ADMIN_PASSWORD di .env (minimal 12 karakter) sebelum seeding di production.');
        }

        $admin = User::firstOrNew(['email' => $cfg['email']]);

        if (! $password && ! $admin->exists) {
            $password = Str::password(16, symbols: false);
            $this->command?->warn("Password admin dibuat acak: {$password}");
            $this->command?->warn('Simpan sekarang, atau isi ADMIN_PASSWORD di .env lalu jalankan: php artisan db:seed --class=AdminSeeder');
        }

        $admin->fill([
            'name' => $cfg['name'],
            'phone' => $cfg['phone'],
            'role' => 'admin',
        ]);
        $admin->email_verified_at ??= now();

        if ($password) {
            $admin->password = $password;   // di-hash bcrypt otomatis oleh cast 'hashed'
        }

        $admin->save();
    }
}
