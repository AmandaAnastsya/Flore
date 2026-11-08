<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\DeliverySchedule;

class OrderController extends Controller
{
    public function show(Order $order)
    {
        abort_unless($order->user_id === auth()->id(), 404);

        return view('orders.show', [
            'order' => $order->load('items'),
            'slots' => DeliverySchedule::labels(),
        ]);
    }
}