<?php

namespace Tests\Unit\Services\User;

use App\Models\Address;
use App\Models\User;
use App\Services\User\AddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressServiceTest extends TestCase
{
    use RefreshDatabase;

    private AddressService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AddressService();
    }

    private function addressData(array $overrides = []): array
    {
        return array_merge([
            'label'          => 'Rumah',
            'recipient_name' => 'John',
            'phone'          => '+6281234567890',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Cicendo',
            'postal_code'    => '40172',
            'street'         => 'Jl. Merdeka No. 1',
        ], $overrides);
    }

    public function test_first_stored_address_is_default(): void
    {
        $user    = User::factory()->create();
        $address = $this->service->store($user, $this->addressData());

        $this->assertTrue($address->is_default);
    }

    public function test_second_stored_address_is_not_default(): void
    {
        $user = User::factory()->create();
        $this->service->store($user, $this->addressData());
        $second = $this->service->store($user, $this->addressData(['label' => 'Kantor']));

        $this->assertFalse($second->is_default);
    }

    public function test_set_default_changes_default_address(): void
    {
        $user    = User::factory()->create();
        $first   = $this->service->store($user, $this->addressData());
        $second  = $this->service->store($user, $this->addressData(['label' => 'Kantor']));

        $this->service->setDefault($second);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_delete_soft_deletes_address(): void
    {
        $user    = User::factory()->create();
        $address = Address::factory()->for($user)->create();

        $this->service->delete($address);

        $this->assertSoftDeleted('addresses', ['id' => $address->id]);
    }

    public function test_delete_default_address_promotes_next(): void
    {
        $user    = User::factory()->create();
        $default = Address::factory()->for($user)->default()->create();
        $other   = Address::factory()->for($user)->create();

        $this->service->delete($default);

        $this->assertTrue($other->fresh()->is_default);
    }
}
