<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderStatusLog>
 */
class OrderStatusLogFactory extends Factory
{
    protected $model = OrderStatusLog::class;

    public function definition(): array
    {
        return [
            'order_id'    => Order::factory(),
            'from_status' => null,
            'to_status'   => 'pending',
            'note'        => null,
            'changed_by'  => null,
        ];
    }
}
