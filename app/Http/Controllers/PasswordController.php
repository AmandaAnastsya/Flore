<?php

namespace App\Http\Controllers;

use App\Notifications\PasswordChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Ganti password untuk semua user yang login (customer & admin).
 * Admin memakai halaman di /admin dengan aturan password yang lebih panjang.
 */
class PasswordController extends Controller
{
    public const MIN_CUSTOMER = 8;
    public const MIN_ADMIN = 12;   // sama dengan syarat ADMIN_PASSWORD di AdminSeeder

    public function edit(Request $request)
    {
        // admin yang membuka halaman customer diarahkan ke versi admin (dan sebaliknya)
        if ($request->user()->isAdmin() !== $request->routeIs('admin.*')) {
            return redirect()->route($request->user()->isAdmin() ? 'admin.password.edit' : 'account.password.edit');
        }

        return view($request->user()->isAdmin() ? 'admin.account.password' : 'account.password', [
            'min' => $this->minLength($request),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        abort_if($user->isAdmin() !== $request->routeIs('admin.*'), 403);

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password',
                Password::min($this->minLength($request))->letters()->numbers()],
        ], [
            'password.different' => 'Password baru harus berbeda dari password saat ini.',
        ], [
            'current_password' => 'password saat ini',
            'password' => 'password baru',
        ]);

        $user->forceFill([
            'password' => Hash::make($request->input('password')),
            'remember_token' => Str::random(60),   // cookie "ingat saya" lama tidak berlaku lagi
        ])->save();

        // keluarkan sesi login di perangkat lain, sesi ini tetap aktif
        $request->session()->regenerate();
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())->delete();
        }

        try {
            $user->notify(new PasswordChanged($request->ip()));
        } catch (\Throwable $e) {
            report($e);   // gagal kirim email tidak membatalkan ganti password
        }

        return redirect()->route($user->isAdmin() ? 'admin.password.edit' : 'account.password.edit')
            ->with('success', 'Password berhasil diganti. Sesi login di perangkat lain telah dikeluarkan.');
    }

    private function minLength(Request $request): int
    {
        return $request->user()->isAdmin() ? self::MIN_ADMIN : self::MIN_CUSTOMER;
    }
}
