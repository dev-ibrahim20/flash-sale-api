<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Product;
use Illuminate\Support\Facades\Artisan;

class HoldExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_hold_expires_and_frees_stock()
    {
        // seed product with stock 2
        Product::create(['name'=>'p','price'=>10,'stock'=>2]);

        $product = Product::first();

        // create hold directly in DB (simulate controller)
        $id = \DB::table('holds')->insertGetId([
            'product_id' => $product->id,
            'qty' => 2,
            'expires_at' => now()->addSeconds(1),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // wait for 2 seconds
        sleep(2);

        // run release job via artisan (simulate queue worker)
        Artisan::call('queue:work --once --tries=1');

        $available = $product->available();
        $this->assertEquals(2, $available);
    }
}
