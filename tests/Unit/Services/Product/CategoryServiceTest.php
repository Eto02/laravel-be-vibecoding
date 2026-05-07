<?php

namespace Tests\Unit\Services\Product;

use App\Services\Product\CategoryService;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class CategoryServiceTest extends TestCase
{
    public function test_build_tree_nests_children_correctly(): void
    {
        $service = $this->makePartialService();
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildTree');

        $makeCategory = function (array $attrs) {
            $cat = new \App\Models\Category();
            foreach ($attrs as $k => $v) {
                $cat->$k = $v;
            }
            return $cat;
        };

        $all = new Collection([
            $makeCategory(['id' => 1, 'parent_id' => null, 'name' => 'Electronics', 'slug' => 'electronics', 'icon' => null, 'level' => 1, 'sort_order' => 0]),
            $makeCategory(['id' => 2, 'parent_id' => 1, 'name' => 'Phones', 'slug' => 'phones', 'icon' => null, 'level' => 2, 'sort_order' => 0]),
            $makeCategory(['id' => 3, 'parent_id' => 2, 'name' => 'Android', 'slug' => 'android', 'icon' => null, 'level' => 3, 'sort_order' => 0]),
        ]);

        $tree = $method->invoke($service, $all, null);

        $this->assertCount(1, $tree);
        $this->assertEquals('Electronics', $tree[0]['name']);
        $this->assertCount(1, $tree[0]['children']);
        $this->assertEquals('Phones', $tree[0]['children'][0]['name']);
        $this->assertCount(1, $tree[0]['children'][0]['children']);
        $this->assertEquals('Android', $tree[0]['children'][0]['children'][0]['name']);
    }

    private function makePartialService(): CategoryService
    {
        $cache = new class implements \App\Contracts\Shared\CacheServiceInterface {
            public function remember(string $key, int $ttl, callable $callback): mixed { return $callback(); }
            public function forget(string $key): void {}
            public function has(string $key): bool { return false; }
        };

        return new CategoryService($cache);
    }
}
