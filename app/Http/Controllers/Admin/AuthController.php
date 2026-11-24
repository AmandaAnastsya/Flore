<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // 'role' => 'admin' membuat akun customer otomatis ditolak
        if (Auth::attempt([...$credentials, 'role' => 'admin'], $request->boolean('remember'))) {
            $request->session()->regenerate();

            return self::redirectAfterLogin($request);
        }

        throw ValidationException::withMessages([
            'email' => 'Email/password salah, atau akun ini bukan admin.',
        ]);
    }

    /**
     * Kembali ke halaman admin yang tadinya diminta; URL tujuan dari sesi tamu
     * (mis. /checkout) diabaikan supaya admin tidak mendarat di halaman toko.
     */
    public static function redirectAfterLogin(Request $request)
    {
        $intended = (string) $request->session()->pull('url.intended');

        return redirect(str_starts_with($intended, url('/admin') . '/') ? $intended : route('admin.dashboard'));
    }
}