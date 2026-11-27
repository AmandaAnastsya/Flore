<?php

namespace App\Http\Controllers;

use App\Models\Bouquet;
use App\Models\Florist;
use App\Models\Testimonial;
use App\Services\DeliverySchedule;

/** Halaman informasi toko: Our Story, Care Guide, FAQ & Shipping Policy (mockup hal. 53–55). */
class PageController extends Controller
{
    public function story()
    {
        return view('pages.story', [
            'photo' => Bouquet::visible()->whereNotNull('image')->orderBy('id')->first(),
            'florists' => Florist::active()->orderBy('name')->get(),
            'quote' => Testimonial::with('user')->where('is_approved', true)->latest('approved_at')->first(),
        ]);
    }

    public function care()
    {
        return view('pages.care');
    }

    public function faq()
    {
        return view('pages.faq', [
            'slots' => DeliverySchedule::labels(),
            'leadHours' => DeliverySchedule::leadHours(),
            'maxDays' => DeliverySchedule::maxDays(),
            'bank' => config('flore.payment'),
            'phone' => config('flore.store.phone'),
            'email' => config('flore.store.email'),
        ]);
    }
}
