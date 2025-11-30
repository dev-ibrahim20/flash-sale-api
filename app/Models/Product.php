<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name','price','stock'];

    public function available()
    {
        $reserved = DB::table('holds')
            ->where('product_id', $this->id)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->sum('qty');

        return max(0, $this->stock - $reserved);
    }
}
