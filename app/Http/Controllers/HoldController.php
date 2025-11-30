<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Hold;
use App\Jobs\ReleaseHoldJob;

class HoldController extends Controller
{
    public function create(Request $req)
    {
        $req->validate(['product_id'=>'required|int','qty'=>'required|int|min:1']);
        $productId = $req->product_id;
        $qty = $req->qty;

        $result = DB::transaction(function() use ($productId, $qty) {
            $product = DB::table('products')->where('id', $productId)->lockForUpdate()->first();
            if (!$product) abort(404, 'Product not found');
            $reserved = DB::table('holds')
                ->where('product_id', $productId)
                ->where('used', false)
                ->where('expires_at', '>', now())
                ->sum('qty');
            $available = $product->stock - $reserved;
            if ($qty > $available) {
                abort(409, 'Not enough stock');
            }
            $expires = now()->addMinutes(2);
            $id = DB::table('holds')->insertGetId([
                'product_id' => $productId,
                'qty' => $qty,
                'expires_at' => $expires,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return ['id' => $id, 'expires_at' => $expires];
        }, 5);

        Cache::forget("product:{$productId}:available");

        // dispatch release job to run after TTL
        ReleaseHoldJob::dispatch($result['id'])->delay(now()->addMinutes(2)->addSeconds(2));

        return response()->json(['hold_id' => $result['id'], 'expires_at' => $result['expires_at']]);
    }
}
