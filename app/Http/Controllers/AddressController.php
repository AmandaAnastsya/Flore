<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Buku Alamat customer: simpan alamat penerima yang sering dipakai untuk checkout cepat. */
class AddressController extends Controller
{
    public function index(Request $request)
    {
        return view('account.addresses', [
            'addresses' => $request->user()->addresses()->get(),
            'address' => new Address(),
            'max' => Address::MAX_PER_USER,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->addresses()->count() >= Address::MAX_PER_USER) {
            return back()->with('error', 'Buku alamat maksimal ' . Address::MAX_PER_USER . ' alamat. Hapus alamat yang tidak dipakai dulu.');
        }

        $data = self::validated($request);
        DB::transaction(function () use ($user, $data) {
            $first = ! $user->addresses()->exists();
            $address = $user->addresses()->create($data + ['is_default' => false]);
            if ($first || $data['is_default']) {
                self::makeDefault($address);
            }
        });

        return redirect()->route('account.addresses.index')->with('success', "Alamat \"{$data['label']}\" disimpan.");
    }

    public function edit(Request $request, Address $address)
    {
        $this->authorizeOwner($request, $address);

        return view('account.address-edit', ['address' => $address]);
    }

    public function update(Request $request, Address $address)
    {
        $this->authorizeOwner($request, $address);
        $data = self::validated($request);

        DB::transaction(function () use ($address, $data) {
            $address->update(collect($data)->except('is_default')->all());
            if ($data['is_default']) {
                self::makeDefault($address);
            }
        });

        return redirect()->route('account.addresses.index')->with('success', 'Alamat diperbarui.');
    }

    public function setDefault(Request $request, Address $address)
    {
        $this->authorizeOwner($request, $address);
        self::makeDefault($address);

        return back()->with('success', "\"{$address->label}\" dijadikan alamat utama.");
    }

    public function destroy(Request $request, Address $address)
    {
        $this->authorizeOwner($request, $address);

        DB::transaction(function () use ($address) {
            $wasDefault = $address->is_default;
            $user = $address->user;
            $address->delete();
            if ($wasDefault && ($next = $user->addresses()->first())) {
                self::makeDefault($next);   // tetap ada satu alamat utama
            }
        });

        return back()->with('success', 'Alamat dihapus.');
    }

    // ------------------------------------------------------------ helper (dipakai juga oleh checkout)

    public static function validated(Request $request, string $prefix = ''): array
    {
        $request->merge([$prefix . 'recipient_phone' => preg_replace('/[\s\-\.]/', '', (string) $request->input($prefix . 'recipient_phone'))]);

        $data = $request->validate([
            $prefix . 'label' => ['required', 'string', 'max:40'],
            $prefix . 'recipient_name' => ['required', 'string', 'max:100'],
            $prefix . 'recipient_phone' => ['required', 'regex:/^(\+62|62|0)8[0-9]{8,12}$/'],
            $prefix . 'city' => ['required', 'string', 'max:100'],
            $prefix . 'address' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            $prefix . 'recipient_phone.regex' => 'Nomor telepon tidak valid (contoh: 081234567890).',
            $prefix . 'address.min' => 'Alamat terlalu singkat, tulis lebih lengkap.',
        ], [
            $prefix . 'label' => 'nama alamat', $prefix . 'recipient_name' => 'nama penerima', $prefix . 'recipient_phone' => 'telepon penerima',
            $prefix . 'city' => 'kota', $prefix . 'address' => 'alamat lengkap',
        ]);

        return collect($data)->mapWithKeys(fn ($v, $k) => [substr($k, strlen($prefix)) => $v])->all()
            + ['is_default' => $request->boolean($prefix . 'is_default')];
    }

    public static function makeDefault(Address $address): void
    {
        Address::where('user_id', $address->user_id)->whereKeyNot($address->id)->update(['is_default' => false]);
        $address->forceFill(['is_default' => true])->save();
    }

    private function authorizeOwner(Request $request, Address $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 404);
    }
}
