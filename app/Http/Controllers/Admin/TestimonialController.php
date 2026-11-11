<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\Request;

/** Approve Testimonial: hanya yang disetujui tampil di halaman depan. */
class TestimonialController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $status = is_string($status) && isset(Testimonial::STATUS_LABELS[$status]) ? $status : 'pending';

        return view('admin.testimonials.index', [
            'testimonials' => Testimonial::with(['user', 'order'])->withStatus($status)->latest()->paginate(15)->withQueryString(),
            'status' => $status,
            'counts' => collect(array_keys(Testimonial::STATUS_LABELS))
                ->mapWithKeys(fn ($s) => [$s => Testimonial::withStatus($s)->count()]),
        ]);
    }

    public function approve(Testimonial $testimonial)
    {
        $testimonial->update(['is_approved' => true, 'approved_at' => now(), 'rejected_at' => null]);

        return back()->with('success', 'Testimonial disetujui dan tampil di halaman depan.');
    }

    public function reject(Testimonial $testimonial)
    {
        $testimonial->update(['is_approved' => false, 'approved_at' => null, 'rejected_at' => now()]);

        return back()->with('success', 'Testimonial ditolak.');
    }
}
