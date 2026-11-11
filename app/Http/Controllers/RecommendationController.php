<?php

namespace App\Http\Controllers;

use App\Services\RecommendationEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    /** Buka Home (customer) atau landing (tamu) dengan modal wizard langsung tampil. */
    public function index(): RedirectResponse
    {
        $user = auth()->user();

        return $user && ! $user->isAdmin()
            ? redirect()->route('home', ['wizard' => 1])
            : redirect()->route('landing', ['wizard' => 1]);
    }

    /** Dipanggil wizard (AJAX) di langkah terakhir. */
    public function recommend(Request $request, RecommendationEngine $engine): JsonResponse
    {
        $answers = $request->validate([
            'occasion' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:50'],
            'style' => ['nullable', 'string', 'max:50'],
            'size' => ['nullable', 'string', 'max:50'],
            'budget' => ['nullable', 'string', 'max:50'],
        ]);

        $result = $engine->recommend($answers);

        return response()->json([
            'mode' => $result->mode,
            'message' => $result->message,
            'total' => $result->total,
            'items' => $result->items->map(function (array $item) {
                $bouquet = $item['bouquet'];

                return [
                    'name' => $bouquet->name,
                    'category' => $bouquet->category?->name,
                    'url' => route('catalog.show', $bouquet),
                    'cart_url' => route('cart.store', $bouquet),
                    'image' => $bouquet->image_url,
                    'price' => 'Rp ' . number_format($item['price'], 0, ',', '.'),
                    'percent' => $item['percent'],
                    'is_perfect' => $item['is_perfect'],
                    'within_budget' => $item['within_budget'],
                    'reasons' => $item['reasons'],
                    'low_stock' => $bouquet->isLowStock(),
                ];
            })->values(),
        ]);
    }
}
