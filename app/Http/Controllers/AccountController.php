<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Notifications\EmailChanged;
use App\Services\AvatarService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Profil Saya & Pengaturan Akun untuk semua user (customer & admin).
 * Route customer di /account, route admin di /admin/account (layout admin).
 */
class AccountController extends Controller
{
    /** Status pesanan yang masih berjalan — akun tidak bisa dihapus selama ada pesanan ini. */
    private const OPEN_STATUSES = ['pending_payment', 'payment_confirmed', 'preparing', 'out_for_delivery'];

    // ================================================================ Profil Saya

    public function profile(Request $request)
    {
        if ($redirect = $this->rightArea($request, 'profile')) {
            return $redirect;
        }

        $user = $request->user();
        $stats = $user->isAdmin() ? null : [
            'orders' => $user->orders()->count(),
            'spent' => (float) $user->orders()->paid()->sum('total_amount'),
            'moments' => $user->specialMoments()->count(),
        ];

        return view($user->isAdmin() ? 'admin.account.profile' : 'account.profile', compact('user', 'stats'));
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $this->guardArea($request);
        $emailChanged = strcasecmp((string) $request->input('email'), $user->email) !== 0;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => [$user->isAdmin() ? 'nullable' : 'required', 'regex:/^(\+62|62|0)8[0-9]{8,12}$/'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => [Rule::requiredIf($emailChanged), 'nullable', 'current_password'],
        ], [
            'phone.regex' => 'Nomor HP tidak valid (contoh: 081234567890).',
            'current_password.required' => 'Masukkan password saat ini untuk mengganti email.',
        ], ['current_password' => 'password saat ini']);

        $oldEmail = $user->email;
        $user->fill(['name' => $data['name'], 'phone' => $data['phone'] ?? null, 'email' => Str::lower($data['email'])])->save();

        if ($emailChanged) {
            // beri tahu alamat lama, supaya pemilik akun tahu bila ini bukan dia
            try {
                Notification::route('mail', $oldEmail)->notify(new EmailChanged($user->name, $user->email));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route($this->routeName($request, 'profile'))
            ->with('success', $emailChanged ? 'Profil disimpan. Email login kamu sekarang ' . $user->email . '.' : 'Profil disimpan.');
    }

    public function updateAvatar(Request $request, AvatarService $avatars)
    {
        $this->guardArea($request);
        $request->validate(['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072', 'dimensions:min_width=64,min_height=64']],
            ['avatar.max' => 'Ukuran foto maksimal 3 MB.', 'avatar.dimensions' => 'Foto minimal 64 × 64 piksel.'],
            ['avatar' => 'foto profil']);

        try {
            $avatars->store($request->user(), $request->file('avatar'));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['avatar' => $e->getMessage()]);
        }

        return back()->with('success', 'Foto profil diperbarui.');
    }

    public function destroyAvatar(Request $request, AvatarService $avatars)
    {
        $this->guardArea($request);
        $avatars->delete($request->user());

        return back()->with('success', 'Foto profil dihapus.');
    }

    // ================================================================ Pengaturan Akun

    public function settings(Request $request)
    {
        if ($redirect = $this->rightArea($request, 'settings')) {
            return $redirect;
        }

        $user = $request->user();

        return view($user->isAdmin() ? 'admin.account.settings' : 'account.settings', [
            'user' => $user,
            'sessions' => $this->sessions($request),
            'sessionsSupported' => config('session.driver') === 'database',
            'openOrders' => $user->isAdmin() ? 0 : $user->orders()->whereIn('status', self::OPEN_STATUSES)->count(),
        ]);
    }

    /** Preferensi email update status pesanan (customer). Invoice & email penting tetap dikirim. */
    public function updateNotifications(Request $request)
    {
        abort_if($request->user()->isAdmin(), 403);
        $request->user()->forceFill(['order_updates_enabled' => $request->boolean('order_updates_enabled')])->save();

        return back()->with('success', 'Pengaturan notifikasi disimpan.');
    }

    public function revokeSession(Request $request, string $session)
    {
        $this->guardArea($request);
        abort_if($session === $request->session()->getId(), 422, 'Tidak bisa mengeluarkan sesi yang sedang dipakai.');

        $deleted = DB::table(config('session.table', 'sessions'))
            ->where('id', $session)->where('user_id', $request->user()->id)->delete();

        return back()->with($deleted ? 'success' : 'error', $deleted ? 'Perangkat dikeluarkan.' : 'Sesi tidak ditemukan.');
    }

    public function logoutOtherSessions(Request $request)
    {
        $this->guardArea($request);
        $request->validateWithBag('sessions', ['password' => ['required', 'current_password']], [], ['password' => 'password']);

        $count = DB::table(config('session.table', 'sessions'))->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())->delete();
        $request->user()->forceFill(['remember_token' => Str::random(60)])->save();

        return back()->with('success', "{$count} perangkat lain dikeluarkan.");
    }

    /**
     * Hapus akun (customer). Data pribadi dianonimkan; riwayat pesanan tetap disimpan tanpa identitas
     * karena dibutuhkan untuk pembukuan toko.
     */
    public function destroy(Request $request)
    {
        $user = $request->user();
        abort_if($user->isAdmin(), 403);

        $request->validateWithBag('deleteAccount', [
            'password' => ['required', 'current_password'],
            'confirmation' => ['required', 'in:HAPUS'],
        ], ['confirmation.in' => 'Ketik HAPUS (huruf kapital) untuk konfirmasi.'], ['password' => 'password', 'confirmation' => 'konfirmasi']);

        if ($user->orders()->whereIn('status', self::OPEN_STATUSES)->exists()) {
            return back()->with('error', 'Masih ada pesanan yang sedang berjalan. Akun bisa dihapus setelah semua pesanan selesai atau dibatalkan.');
        }

        DB::transaction(function () use ($user) {
            app(AvatarService::class)->delete($user);
            $user->specialMoments()->delete();
            $user->reminders()->delete();
            \App\Models\WizardQuery::where('user_id', $user->id)->update(['user_id' => null]);
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();

            $user->forceFill([
                'name' => 'Pengguna dihapus',
                'email' => "deleted-{$user->id}-" . Str::lower(Str::random(6)) . '@deleted.flore.invalid',
                'phone' => null, 'date_of_birth' => null, 'anniversary_date' => null,
                'reminders_enabled' => false,
                'password' => Hash::make(Str::random(40)),
                'remember_token' => null,
            ])->save();
        });

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing')->with('success', 'Akunmu sudah dihapus. Terima kasih sudah berbelanja di Floré.');
    }

    // ================================================================ helper

    /** Daftar perangkat yang sedang login (driver session database). */
    private function sessions(Request $request): \Illuminate\Support\Collection
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        return DB::table(config('session.table', 'sessions'))->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'device' => self::describeAgent((string) $s->user_agent),
                'ip' => $s->ip_address,
                'last_active' => Carbon::createFromTimestamp($s->last_activity)->setTimezone(config('app.timezone')),
                'current' => $s->id === $request->session()->getId(),
            ]);
    }

    /** "Chrome di Windows", "Safari di iPhone", … dari user agent. */
    public static function describeAgent(string $ua): string
    {
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Browser lain',
        };
        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'perangkat tidak dikenal',
        };

        return "{$browser} di {$os}";
    }

    /** Admin memakai halaman /admin/account…, customer /account…. */
    private function rightArea(Request $request, string $page)
    {
        return $request->user()->isAdmin() !== $request->routeIs('admin.*')
            ? redirect()->route($this->routeName($request, $page, $request->user()->isAdmin()))
            : null;
    }

    private function guardArea(Request $request): void
    {
        abort_if($request->user()->isAdmin() !== $request->routeIs('admin.*'), 403);
    }

    private function routeName(Request $request, string $page, ?bool $admin = null): string
    {
        return (($admin ?? $request->user()->isAdmin()) ? 'admin.' : '') . 'account.' . $page;
    }
}
