<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ProcessWebhookEventJob implements ShouldQueue
{
    use Queueable;

    public $key;

    public function __construct($key)
    {
        $this->key = $key;
    }

    public function handle()
    {
        $event = DB::table('webhook_events')->where('key', $this->key)->first();
        if (!$event) return;

        $payload = json_decode($event->payload, true);

        // Try finding order
        $order = null;
        if (!empty($payload['order_id'])) {
            $order = DB::table('orders')->where('id', $payload['order_id'])->first();
        } elseif (!empty($payload['hold_id'])) {
            $order = DB::table('orders')->where('hold_id', $payload['hold_id'])->first();
        }

        if (!$order) {
            // re-dispatch to try later (simple backoff)
            self::dispatch($this->key)->delay(now()->addSeconds(5));
            return;
        }

        // Process same as controller: lock and update
        DB::transaction(function() use ($payload, $event, $order) {
            $orderRow = DB::table('orders')->where('id', $order->id)->lockForUpdate()->first();
            if ($orderRow->status === 'paid') {
                DB::table('webhook_events')->where('key', $event->key)->update(['processed_at' => now()]);
                return;
            }

            if ($payload['status'] === 'success') {
                $product = DB::table('products')->where('id', $orderRow->product_id)->lockForUpdate()->first();
                if ($product->stock < $orderRow->qty) {
                    DB::table('orders')->where('id', $orderRow->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
                } else {
                    DB::table('products')->where('id', $product->id)->decrement('stock', $orderRow->qty);
                    DB::table('orders')->where('id', $orderRow->id)->update(['status' => 'paid', 'payment_id' => $payload['payment_id'] ?? null, 'updated_at' => now()]);
                }
            } else {
                DB::table('orders')->where('id', $orderRow->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
            }

            DB::table('webhook_events')->where('key', $event->key)->update(['processed_at' => now()]);
        });

        Cache::forget("product:{$order->product_id}:available");
    }
}
