<?php

namespace App\Http\Controllers;

use App\Models\Flower;
use Illuminate\Http\Request;

class StemController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->query('q');
        $q = is_string($q) ? mb_substr(trim($q), 0, 100) : '';

        $flowers = Flower::active()
            ->when($q, function ($query, $term) {
                $like = '%' . addcslashes($term, '%_\\') . '%';
                $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('species', 'like', $like));
            })
            ->when($request->boolean('in_stock'), fn ($query) => $query->where('stock', '>', 0))
            ->orderBy('species')
            ->orderBy('name')
            ->get();

        return view('stems.index', ['flowers' => $flowers, 'q' => $q]);
    }
}