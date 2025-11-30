<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class OrderController extends Controller
{
    public function create(Request $req)
    {
        $req->validate(['hold_id'=>'required|int']);
        $holdId = $req->hold_id;

        $order = DB::transaction(function() use ($holdId) {
            $hold = DB::table('holds')->where('id', $holdId)->lockForUpdate()->first();
            if (!$hold) abort(404, 'Hold not found');
            if ($hold->used) abort(409, 'Hold already used');
            if ($hold->expires_at <= now()) abort(410, 'Hold expired');

            $product = DB::table('products')->where('id', $hold->product_id)->first();
            if (!$product) abort(404, 'Product not found');

            $total = $product->price * $hold->qty;
            $orderId = DB::table('orders')->insertGetId([
                'hold_id' => $hold->id,
                'product_id' => $hold->product_id,
                'qty' => $hold->qty,
                'total' => $total,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('holds')->where('id', $hold->id)->update(['used' => true, 'updated_at' => now()]);

            return DB::table('orders')->where('id', $orderId)->first();
        });

        Cache::forget("product:{$order->product_id}:available");

        return response()->json($order, 201);
    }
}
