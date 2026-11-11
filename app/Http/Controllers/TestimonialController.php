<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

/** FR-09: customer mengirim testimonial untuk pesanan yang sudah terkirim (menunggu persetujuan admin). */
class TestimonialController extends Controller
{
    public function store(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        if ($order->status !== 'delivered') {
            return back()->with('error', 'Testimonial bisa dikirim setelah pesanan diterima.');
        }

        if ($order->testimonial()->exists()) {
            return back()->with('error', 'Kamu sudah mengirim testimonial untuk pesanan ini.');
        }

        $data = $request->validateWithBag('testimonial', [
            'rating' => ['required', 'integer', 'between:1,5'],
            'content' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'rating.required' => 'Pilih rating 1–5.',
            'content.required' => 'Tulis pengalamanmu.',
            'content.min' => 'Ceritakan sedikit lebih panjang (minimal 10 karakter).',
        ]);

        $order->testimonial()->create($data + ['user_id' => $order->user_id]);

        return back()->with('success', 'Terima kasih! Testimonialmu akan tampil setelah disetujui admin.');
    }
}
