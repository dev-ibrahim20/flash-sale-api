<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function show($id)
    {
        $cacheKey = "product:{$id}:available";
        $data = Cache::remember($cacheKey, 2, function() use ($id) {
            $product = Product::findOrFail($id);
            return [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'available' => $product->available(),
            ];
        });
        return response()->json($data);
    }
}
