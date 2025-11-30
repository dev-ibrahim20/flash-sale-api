<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ReleaseHoldJob implements ShouldQueue
{
    use Queueable;

    public $holdId;

    public function __construct($holdId)
    {
        $this->holdId = $holdId;
    }

    public function handle()
    {
        $lockKey = "release-hold-{$this->holdId}";
        // simple cache lock (requires cache driver that supports locks, e.g., redis)
        $lock = Cache::lock($lockKey, 10);

        if ($lock->get()) {
            try {
                DB::transaction(function() {
                    $hold = DB::table('holds')->where('id', $this->holdId)->first();
                    if (!$hold) return;
                    if ($hold->used) return;
                    if ($hold->expires_at > now()) return;
                    // mark used=true to prevent counting as active
                    DB::table('holds')->where('id', $this->holdId)->update(['used' => true, 'updated_at' => now()]);
                    Cache::forget("product:{$hold->product_id}:available");
                });
            } finally {
                $lock->release();
            }
        }
    }
}
