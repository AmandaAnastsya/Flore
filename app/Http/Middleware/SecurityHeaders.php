<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan dasar untuk semua halaman web.
 * - X-Frame-Options: cegah clickjacking (situs lain menyematkan Floré dalam iframe)
 * - X-Content-Type-Options: browser tidak menebak tipe file (mis. file upload dijalankan sebagai skrip)
 * - Referrer-Policy: URL lengkap (berisi no. pesanan) tidak bocor ke situs lain
 * - Permissions-Policy: kamera/mikrofon/lokasi tidak dipakai, jadi dimatikan
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}
