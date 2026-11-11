<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Models\SpecialMoment;
use App\Services\ReminderPlanner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Profil customer + input momen spesial untuk Smart Reminder System. */
class ProfileController extends Controller
{
    public function edit(ReminderPlanner $planner)
    {
        $user = auth()->user()->load('specialMoments');

        return view('profile.edit', [
            'user' => $user,
            'moments' => $user->specialMoments->sortBy(fn ($m) => $m->date->format('m-d'))->values(),
            'upcoming' => $planner->upcomingFor($user)->take(6),
            'dayOptions' => ReminderPlanner::DAY_OPTIONS,
            'types' => SpecialMoment::TYPES,
            'maxMoments' => SpecialMoment::MAX_PER_USER,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'regex:/^(\+62|62|0)8[0-9]{8,12}$/'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'anniversary_date' => ['nullable', 'date', 'before_or_equal:today'],
            'reminder_days' => ['required', 'integer', Rule::in(ReminderPlanner::DAY_OPTIONS)],
        ], [
            'phone.regex' => 'Nomor HP tidak valid (contoh: 081234567890).',
            'date_of_birth.before' => 'Tanggal lahir harus sebelum hari ini.',
            'anniversary_date.before_or_equal' => 'Isi tanggal anniversary yang sudah terjadi (tahun pertama), kami ingatkan setiap tahunnya.',
        ]);

        $data['reminders_enabled'] = $request->boolean('reminders_enabled');

        $user->fill($data);
        $datesChanged = $user->isDirty(['date_of_birth', 'anniversary_date']);
        $user->save();

        // tanggal berubah → pengingat lama yang belum terkirim tidak berlaku lagi
        if ($datesChanged) {
            $user->reminders()->where('status', 'pending')
                ->where(fn ($q) => $q->where('dedupe_key', 'like', "u{$user->id}-birthday:%")
                    ->orWhere('dedupe_key', 'like', "u{$user->id}-anniversary:%"))
                ->delete();
        }

        return redirect()->route('profile.edit')->with('success', 'Profil dan pengaturan pengingat disimpan.');
    }

    public function storeMoment(Request $request)
    {
        $user = $request->user();

        if ($user->specialMoments()->count() >= SpecialMoment::MAX_PER_USER) {
            return back()->with('error', 'Maksimal ' . SpecialMoment::MAX_PER_USER . ' momen spesial.');
        }

        $data = $request->validateWithBag('moment', [
            'type' => ['required', Rule::in(array_keys(SpecialMoment::TYPES))],
            'title' => ['required', 'string', 'max:100'],
            'date' => ['required', 'date'],
        ], [
            'title.required' => 'Tulis nama momennya, mis. "Ulang Tahun Ibu".',
            'date.required' => 'Pilih tanggal momen.',
        ]);

        $user->specialMoments()->create($data);

        return redirect()->route('profile.edit')->with('success', "Momen \"{$data['title']}\" ditambahkan. Kami akan mengingatkanmu setiap tahun.");
    }

    public function destroyMoment(SpecialMoment $moment)
    {
        abort_unless($moment->user_id === auth()->id(), 404);

        Reminder::where('special_moment_id', $moment->id)->where('status', 'pending')->delete();
        $moment->delete();

        return redirect()->route('profile.edit')->with('success', 'Momen dihapus.');
    }
}
