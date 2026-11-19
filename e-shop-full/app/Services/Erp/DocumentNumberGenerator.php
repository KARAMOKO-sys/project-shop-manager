<?php

namespace App\Services\Erp;

use App\Models\DocumentSequence;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

/**
 * Hands out gap-free, per-shop document numbers such as FAC-2026-00001 or PO-00001.
 */
class DocumentNumberGenerator
{
    public function next(Shop $shop, string $key, string $prefix, int $padding = 5): string
    {
        return DB::transaction(function () use ($shop, $key, $prefix, $padding): string {
            $sequence = DocumentSequence::query()->where('shop_id', $shop->id)->where('key', $key)->lockForUpdate()->first()
                ?? DocumentSequence::create(['shop_id' => $shop->id, 'key' => $key, 'next_number' => 1]);

            $number = $sequence->next_number;
            $sequence->increment('next_number');

            return $prefix.str_pad((string) $number, $padding, '0', STR_PAD_LEFT);
        });
    }
}
