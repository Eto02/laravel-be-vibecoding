<?php

namespace Tests\Feature\Api\User;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        return User::factory()->create();
    }

    private function addressPayload(array $overrides = []): array
    {
        return array_merge([
            'label'          => 'Rumah',
            'recipient_name' => 'John Doe',
            'phone'          => '+6281234567890',
            'province'       => 'Jawa Barat',
            'city'           => 'Bandung',
            'district'       => 'Cicendo',
            'postal_code'    => '40172',
            'street'         => 'Jl. Merdeka No. 1',
        ], $overrides);
    }

    // ── GET /users/me/addresses ────────────────────────────────────────────────

    public function test_list_addresses_requires_authentication(): void
    {
        $this->getJson('/api/users/me/addresses')->assertStatus(401);
    }

    public function test_list_addresses_returns_only_own_addresses(): void
    {
        $user  = $this->actingUser();
        $other = $this->actingUser();

        Address::factory()->for($user)->count(2)->create();
        Address::factory()->for($other)->count(3)->create();

        $this->actingAs($user)->getJson('/api/users/me/addresses')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    // ── POST /users/me/addresses ───────────────────────────────────────────────

    public function test_store_address_creates_successfully(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)->postJson('/api/users/me/addresses', $this->addressPayload())
            ->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'label', 'recipient_name', 'phone', 'province', 'city', 'district', 'postal_code', 'street', 'is_default'],
            ]);
    }

    public function test_first_address_is_set_as_default(): void
    {
        $user = $this->actingUser();

        $response = $this->actingAs($user)
            ->postJson('/api/users/me/addresses', $this->addressPayload());

        $response->assertStatus(201)
            ->assertJson(['data' => ['is_default' => true]]);
    }

    public function test_second_address_is_not_default(): void
    {
        $user = $this->actingUser();
        Address::factory()->for($user)->default()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/users/me/addresses', $this->addressPayload(['label' => 'Kantor']));

        $response->assertStatus(201)
            ->assertJson(['data' => ['is_default' => false]]);
    }

    public function test_store_address_validates_required_fields(): void
    {
        $this->actingAs($this->actingUser())
            ->postJson('/api/users/me/addresses', [])
            ->assertStatus(422);
    }

    // ── GET /users/me/addresses/{id} ───────────────────────────────────────────

    public function test_show_own_address_succeeds(): void
    {
        $user    = $this->actingUser();
        $address = Address::factory()->for($user)->create();

        $this->actingAs($user)->getJson("/api/users/me/addresses/{$address->id}")
            ->assertStatus(200)
            ->assertJson(['data' => ['id' => $address->id]]);
    }

    public function test_show_other_users_address_returns_403(): void
    {
        $owner = $this->actingUser();
        $other = $this->actingUser();
        $address = Address::factory()->for($owner)->create();

        $this->actingAs($other)->getJson("/api/users/me/addresses/{$address->id}")
            ->assertStatus(403);
    }

    // ── PUT /users/me/addresses/{id} ───────────────────────────────────────────

    public function test_update_address_succeeds(): void
    {
        $user    = $this->actingUser();
        $address = Address::factory()->for($user)->create();

        $this->actingAs($user)
            ->putJson("/api/users/me/addresses/{$address->id}", ['label' => 'Kantor'])
            ->assertStatus(200)
            ->assertJson(['data' => ['label' => 'Kantor']]);
    }

    public function test_update_other_users_address_returns_403(): void
    {
        $owner   = $this->actingUser();
        $other   = $this->actingUser();
        $address = Address::factory()->for($owner)->create();

        $this->actingAs($other)
            ->putJson("/api/users/me/addresses/{$address->id}", ['label' => 'Kantor'])
            ->assertStatus(403);
    }

    // ── DELETE /users/me/addresses/{id} ────────────────────────────────────────

    public function test_delete_address_returns_204(): void
    {
        $user    = $this->actingUser();
        $address = Address::factory()->for($user)->create();

        $this->actingAs($user)
            ->deleteJson("/api/users/me/addresses/{$address->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted('addresses', ['id' => $address->id]);
    }

    public function test_delete_other_users_address_returns_403(): void
    {
        $owner   = $this->actingUser();
        $other   = $this->actingUser();
        $address = Address::factory()->for($owner)->create();

        $this->actingAs($other)
            ->deleteJson("/api/users/me/addresses/{$address->id}")
            ->assertStatus(403);
    }

    // ── POST /users/me/addresses/{id}/set-default ──────────────────────────────

    public function test_set_default_updates_correctly(): void
    {
        $user     = $this->actingUser();
        $default  = Address::factory()->for($user)->default()->create();
        $newDefault = Address::factory()->for($user)->create();

        $this->actingAs($user)
            ->postJson("/api/users/me/addresses/{$newDefault->id}/set-default")
            ->assertStatus(200)
            ->assertJson(['data' => ['is_default' => true]]);

        $this->assertDatabaseHas('addresses', ['id' => $default->id, 'is_default' => false]);
        $this->assertDatabaseHas('addresses', ['id' => $newDefault->id, 'is_default' => true]);
    }

    public function test_set_default_other_users_address_returns_403(): void
    {
        $owner   = $this->actingUser();
        $other   = $this->actingUser();
        $address = Address::factory()->for($owner)->create();

        $this->actingAs($other)
            ->postJson("/api/users/me/addresses/{$address->id}/set-default")
            ->assertStatus(403);
    }
}
