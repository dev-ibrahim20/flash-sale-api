<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Product;

class WebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_processed_once()
    {
        $product = Product::create(['name'=>'p','price'=>10,'stock'=>5]);

        // create hold and order
        $holdId = \DB::table('holds')->insertGetId([
            'product_id' => $product->id,
            'qty' => 1,
            'expires_at' => now()->addMinutes(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = \DB::table('orders')->insertGetId([
            'hold_id' => $holdId,
            'product_id' => $product->id,
            'qty' => 1,
            'total' => 10,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'idempotency_key' => 'key-123',
            'status' => 'success',
            'order_id' => $orderId,
            'payment_id' => 'pay-1',
        ];

        $this->postJson('/api/payments/webhook', $payload)->assertStatus(200);
        $this->postJson('/api/payments/webhook', $payload)->assertStatus(200);

        $this->assertEquals(4, \DB::table('products')->first()->stock); // decremented once
    }
}
