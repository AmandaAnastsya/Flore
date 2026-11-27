<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StudioSettings;
use Illuminate\Http\Request;

/** Studio Settings: profil toko, rekening, aturan pengiriman, jam reminder, umur segar bunga. */
class SettingsController extends Controller
{
    public function edit(StudioSettings $settings)
    {
        return view('admin.settings.edit', ['values' => $settings->all()]);
    }

    public function update(Request $request, StudioSettings $settings)
    {
        $rules = $attributes = [];
        foreach (StudioSettings::flat() as $key => [$label, $type, $validation]) {
            $field = str_replace('.', '__', $key);   // titik di nama input dianggap array oleh Laravel
            $rules[$field] = $validation;
            $attributes[$field] = strtolower($label);
        }

        $data = $request->validate($rules, [
            'store__phone.regex' => 'Nomor WhatsApp harus nomor Indonesia, contoh 081234567890.',
            'payment__account_number.regex' => 'Nomor rekening hanya boleh angka, spasi, atau tanda minus.',
        ], $attributes);

        $settings->save(collect($data)->mapWithKeys(fn ($v, $k) => [str_replace('__', '.', $k) => $v])->all());

        return redirect()->route('admin.settings.edit')->with('success', 'Pengaturan studio disimpan dan langsung berlaku.');
    }
}
