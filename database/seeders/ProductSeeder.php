<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run()
    {
        Product::create([
            'name' => 'Flash Widget',
            'price' => 49.99,
            'stock' => 10,
        ]);
    }
}
