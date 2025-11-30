<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\WebhookEvent;
use App\Jobs\ProcessWebhookEventJob;

class WebhookController extends Controller
{
    public function handle(Request $req)
    {
        $req->validate([
            'idempotency_key' => 'required|string',
            'status' => 'required|in:success,failure',
            'order_id' => 'nullable|int',
            'hold_id' => 'nullable|int',
            'payment_id' => 'nullable|string',
        ]);

        $key = $req->input('idempotency_key');

        // Try insert unique webhook event (acts as dedupe marker)
        try {
            DB::table('webhook_events')->insert([
                'key' => $key,
                'payload' => json_encode($req->all()),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // duplicate key -> already processed or in progress
            $existing = DB::table('webhook_events')->where('key', $key)->first();
            if ($existing && $existing->processed_at) {
                return response()->json(['status' => 'ok', 'message' => 'already processed']);
            }
            // else continue to processing
        }

        // Try immediate processing
        $order = null;
        if ($req->filled('order_id')) {
            $order = DB::table('orders')->where('id', $req->order_id)->first();
        } elseif ($req->filled('hold_id')) {
            $order = DB::table('orders')->where('hold_id', $req->hold_id)->first();
        }

        if (!$order) {
            // order not created yet -> queue a job to retry later
            ProcessWebhookEventJob::dispatch($key)->delay(now()->addSeconds(5));
            return response()->json(['status' => 'accepted', 'message' => 'will retry'], 202);
        }

        // process synchronously
        DB::transaction(function() use ($req, $key, $order) {
            $orderRow = DB::table('orders')->where('id', $order->id)->lockForUpdate()->first();
            if ($orderRow->status === 'paid') {
                DB::table('webhook_events')->where('key', $key)->update(['processed_at' => now()]);
                return;
            }

            if ($req->status === 'success') {
                $product = DB::table('products')->where('id', $orderRow->product_id)->lockForUpdate()->first();
                if ($product->stock < $orderRow->qty) {
                    DB::table('orders')->where('id', $orderRow->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
                } else {
                    DB::table('products')->where('id', $product->id)->decrement('stock', $orderRow->qty);
                    DB::table('orders')->where('id', $orderRow->id)->update(['status' => 'paid', 'payment_id' => $req->payment_id, 'updated_at' => now()]);
                }
            } else {
                DB::table('orders')->where('id', $orderRow->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
            }

            DB::table('webhook_events')->where('key', $key)->update(['processed_at' => now()]);
        });

        Cache::forget("product:{$order->product_id}:available");

        return response()->json(['status' => 'ok']);
    }
}
