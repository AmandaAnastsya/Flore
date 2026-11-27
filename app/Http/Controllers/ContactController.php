<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Notifications\ContactReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/** Contact Us (Use Case hal. 34) — terbuka untuk tamu & customer. */
class ContactController extends Controller
{
    public function show(Request $request)
    {
        return view('pages.contact', [
            'user' => $request->user(),
            'orderNumber' => is_string($request->query('order')) ? mb_substr($request->query('order'), 0, 30) : null,
            'phone' => config('flore.store.phone'),
            'email' => config('flore.store.email'),
        ]);
    }

    public function store(Request $request)
    {
        // jebakan bot: kolom tersembunyi yang tidak diisi manusia
        if (filled($request->input('website'))) {
            return redirect()->route('contact.show')->with('success', 'Terima kasih! Pesanmu sudah terkirim.');
        }

        $request->merge(['phone' => preg_replace('/[\s\-\.]/', '', (string) $request->input('phone')) ?: null]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'regex:/^(\+62|62|0)8[0-9]{8,12}$/'],
            'topic' => ['required', Rule::in(array_keys(ContactMessage::TOPICS))],
            'order_number' => ['nullable', 'regex:/^FLR-\d{6}-[A-Z0-9]{5}$/i'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'phone.regex' => 'Nomor HP tidak valid (contoh: 081234567890).',
            'order_number.regex' => 'Format nomor pesanan seperti FLR-261124-AB12C.',
            'message.min' => 'Ceritakan sedikit lebih lengkap (minimal 10 karakter).',
        ], ['name' => 'nama', 'topic' => 'topik', 'order_number' => 'nomor pesanan', 'message' => 'pesan']);

        $data['order_number'] = isset($data['order_number']) ? strtoupper($data['order_number']) : null;
        $contact = ContactMessage::create($data + ['user_id' => $request->user()?->id]);

        try {
            Notification::route('mail', $contact->email)->notify(new ContactReceived($contact));
            if ($storeEmail = config('flore.store.email')) {
                Notification::route('mail', $storeEmail)->notify(new ContactReceived($contact, forStore: true));
            }
        } catch (\Throwable $e) {
            report($e);   // pesan tetap tersimpan di kotak masuk admin
        }

        return redirect()->route('contact.show')->with('success', 'Terima kasih! Pesanmu sudah terkirim. Kami akan membalas ke ' . $contact->email . '.');
    }
}
