<?php

namespace App\Observers;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class ProductVariantObserver
{
    public function created(ProductVariant $variant): void
    {
        $this->syncProductCounters($variant->product_id);
    }

    public function updated(ProductVariant $variant): void
    {
        $this->syncProductCounters($variant->product_id);
    }

    public function deleted(ProductVariant $variant): void
    {
        $this->syncProductCounters($variant->product_id);
    }

    private function syncProductCounters(int $productId): void
    {
        $agg = DB::table('product_variants')
            ->where('product_id', $productId)
            ->whereNull('deleted_at')
            ->selectRaw('MIN(price) as min_price, MAX(price) as max_price, SUM(stock) as total_stock')
            ->first();

        DB::table('products')->where('id', $productId)->update([
            'min_price'   => $agg->min_price ?? 0,
            'max_price'   => $agg->max_price ?? 0,
            'total_stock' => $agg->total_stock ?? 0,
            'updated_at'  => now(),
        ]);
    }
}
