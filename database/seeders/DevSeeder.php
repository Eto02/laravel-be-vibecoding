<?php

namespace Database\Seeders;

use App\Enums\KycStatus;
use App\Enums\MerchantStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DevSeeder extends Seeder
{
    public function run(): void
    {
        // ── Admin ─────────────────────────────────────────────────────────────
        $admin = User::firstOrCreate(['email' => 'admin@marketplace.dev'], [
            'name'              => 'Super Admin',
            'password'          => Hash::make('password123'),
            'role'              => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        // ── Categories ────────────────────────────────────────────────────────
        $elektronik = $this->category('Elektronik', 'elektronik', 1);
        $fashion     = $this->category('Fashion', 'fashion', 2);
        $olahraga    = $this->category('Olahraga', 'olahraga', 3);

        $hp      = $this->category('Handphone', 'handphone', 1, $elektronik->id);
        $laptop  = $this->category('Laptop', 'laptop', 2, $elektronik->id);
        $sepatu  = $this->category('Sepatu', 'sepatu', 1, $olahraga->id);
        $baju    = $this->category('Baju', 'baju', 1, $fashion->id);

        // ── Merchant user ─────────────────────────────────────────────────────
        $merchant = User::firstOrCreate(['email' => 'merchant@marketplace.dev'], [
            'name'              => 'Merchant Demo',
            'password'          => Hash::make('password123'),
            'role'              => UserRole::Merchant,
            'email_verified_at' => now(),
        ]);

        $store = Store::firstOrCreate(['user_id' => $merchant->id], [
            'name'        => 'Toko Demo',
            'slug'        => 'toko-demo',
            'description' => 'Toko untuk keperluan demo dan testing',
            'status'      => MerchantStatus::Active,
            'kyc_status'  => KycStatus::Approved,
            'city'        => 'Bandung',
            'province'    => 'Jawa Barat',
        ]);

        // ── Products ──────────────────────────────────────────────────────────
        $sepatuProduk = $this->product($store, $sepatu, [
            'name'        => 'Sepatu Lari Pro',
            'slug'        => 'sepatu-lari-pro',
            'description' => 'Sepatu lari berkualitas tinggi cocok untuk marathon dan trail running.',
            'status'      => ProductStatus::Active,
            'weight_gram' => 350,
        ]);

        $this->variant($sepatuProduk, 'SKU-SLP-39', 39000000, 25, ['ukuran' => '39', 'warna' => 'Hitam']);
        $this->variant($sepatuProduk, 'SKU-SLP-40', 39000000, 30, ['ukuran' => '40', 'warna' => 'Hitam']);
        $this->variant($sepatuProduk, 'SKU-SLP-41', 40000000, 20, ['ukuran' => '41', 'warna' => 'Putih']);
        $this->variant($sepatuProduk, 'SKU-SLP-42', 40000000, 15, ['ukuran' => '42', 'warna' => 'Putih']);

        $hpProduk = $this->product($store, $hp, [
            'name'        => 'Smartphone XZ Pro',
            'slug'        => 'smartphone-xz-pro',
            'description' => 'Smartphone flagship dengan kamera 200MP dan baterai 6000mAh.',
            'status'      => ProductStatus::Active,
            'weight_gram' => 210,
        ]);

        $this->variant($hpProduk, 'SKU-XZ-128-BLK', 599900000, 10, ['storage' => '128GB', 'warna' => 'Hitam']);
        $this->variant($hpProduk, 'SKU-XZ-256-BLK', 699900000, 8,  ['storage' => '256GB', 'warna' => 'Hitam']);
        $this->variant($hpProduk, 'SKU-XZ-256-WHT', 699900000, 5,  ['storage' => '256GB', 'warna' => 'Putih']);

        $bajuProduk = $this->product($store, $baju, [
            'name'        => 'Kaos Polos Premium',
            'slug'        => 'kaos-polos-premium',
            'description' => 'Kaos bahan combed 30s, adem dan nyaman dipakai sehari-hari.',
            'status'      => ProductStatus::Active,
            'weight_gram' => 180,
        ]);

        $this->variant($bajuProduk, 'SKU-KPP-S-PTH', 8900000, 50, ['ukuran' => 'S', 'warna' => 'Putih']);
        $this->variant($bajuProduk, 'SKU-KPP-M-PTH', 8900000, 50, ['ukuran' => 'M', 'warna' => 'Putih']);
        $this->variant($bajuProduk, 'SKU-KPP-L-HTM', 8900000, 40, ['ukuran' => 'L', 'warna' => 'Hitam']);
        $this->variant($bajuProduk, 'SKU-KPP-XL-HTM', 9500000, 30, ['ukuran' => 'XL', 'warna' => 'Hitam']);

        // ── Buyer user with cart ───────────────────────────────────────────────
        $buyer = User::firstOrCreate(['email' => 'test@example.com'], [
            'name'              => 'Test User',
            'password'          => Hash::make('password123'),
            'role'              => UserRole::Buyer,
            'email_verified_at' => now(),
        ]);

        // Seed cart with 2 items from the same store
        $cart = Cart::firstOrCreate(['user_id' => $buyer->id]);

        $variantSepatu = ProductVariant::where('sku', 'SKU-SLP-40')->first();
        $variantHp     = ProductVariant::where('sku', 'SKU-XZ-128-BLK')->first();

        if ($variantSepatu && ! $cart->items()->where('product_variant_id', $variantSepatu->id)->exists()) {
            $cart->items()->create([
                'product_variant_id' => $variantSepatu->id,
                'product_id'         => $variantSepatu->product_id,
                'store_id'           => $store->id,
                'quantity'           => 1,
                'price_snapshot'     => $variantSepatu->price,
            ]);
        }

        if ($variantHp && ! $cart->items()->where('product_variant_id', $variantHp->id)->exists()) {
            $cart->items()->create([
                'product_variant_id' => $variantHp->id,
                'product_id'         => $variantHp->product_id,
                'store_id'           => $store->id,
                'quantity'           => 1,
                'price_snapshot'     => $variantHp->price,
            ]);
        }

        // ── Second merchant (untuk test multi-store cart) ──────────────────────
        $merchant2 = User::firstOrCreate(['email' => 'merchant2@marketplace.dev'], [
            'name'              => 'Merchant Dua',
            'password'          => Hash::make('password123'),
            'role'              => UserRole::Merchant,
            'email_verified_at' => now(),
        ]);

        $store2 = Store::firstOrCreate(['user_id' => $merchant2->id], [
            'name'        => 'Toko Gadget',
            'slug'        => 'toko-gadget',
            'description' => 'Toko gadget dan aksesoris elektronik',
            'status'      => MerchantStatus::Active,
            'kyc_status'  => KycStatus::Approved,
            'city'        => 'Jakarta',
            'province'    => 'DKI Jakarta',
        ]);

        $laptopProduk = $this->product($store2, $laptop, [
            'name'        => 'Laptop Ultra Slim',
            'slug'        => 'laptop-ultra-slim',
            'description' => 'Laptop tipis 14 inch, Intel Core i7, RAM 16GB, SSD 512GB.',
            'status'      => ProductStatus::Active,
            'weight_gram' => 1400,
        ]);

        $variantLaptop = $this->variant($laptopProduk, 'SKU-LUS-SLV', 1299900000, 5, ['warna' => 'Silver']);
        $this->variant($laptopProduk, 'SKU-LUS-GLD', 1299900000, 3, ['warna' => 'Gold']);

        // Add laptop to buyer's cart (multi-store scenario)
        if (! $cart->items()->where('product_variant_id', $variantLaptop->id)->exists()) {
            $cart->items()->create([
                'product_variant_id' => $variantLaptop->id,
                'product_id'         => $variantLaptop->product_id,
                'store_id'           => $store2->id,
                'quantity'           => 1,
                'price_snapshot'     => $variantLaptop->price,
            ]);
        }

        $this->command->info('DevSeeder selesai:');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin',     'admin@marketplace.dev',     'password123'],
                ['Merchant',  'merchant@marketplace.dev',  'password123'],
                ['Merchant2', 'merchant2@marketplace.dev', 'password123'],
                ['Buyer',     'test@example.com',          'password123'],
            ]
        );
        $this->command->info('Cart test@example.com: 3 items dari 2 toko (multi-store)');
    }

    private function category(string $name, string $slug, int $sort, ?int $parentId = null): Category
    {
        return Category::firstOrCreate(['slug' => $slug], [
            'parent_id'  => $parentId,
            'name'       => $name,
            'slug'       => $slug,
            'level'      => $parentId ? 2 : 1,
            'sort_order' => $sort,
        ]);
    }

    private function product(Store $store, Category $category, array $attrs): Product
    {
        return Product::firstOrCreate(['slug' => $attrs['slug']], array_merge($attrs, [
            'store_id'    => $store->id,
            'category_id' => $category->id,
            'min_price'   => 0,
            'max_price'   => 0,
            'total_stock' => 0,
            'sold_count'  => 0,
            'rating_avg'  => 0,
        ]));
    }

    private function variant(Product $product, string $sku, int $price, int $stock, array $attributes): ProductVariant
    {
        return ProductVariant::firstOrCreate(['sku' => $sku], [
            'product_id'  => $product->id,
            'sku'         => $sku,
            'price'       => $price,
            'stock'       => $stock,
            'weight_gram' => $product->weight_gram,
            'attributes'  => $attributes,
        ]);
    }
}
