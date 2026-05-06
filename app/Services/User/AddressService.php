<?php

namespace App\Services\User;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AddressService
{
    public function list(User $user): Collection
    {
        return $user->addresses()->orderByDesc('is_default')->orderByDesc('created_at')->get();
    }

    public function store(User $user, array $data): Address
    {
        $isFirst = ! $user->addresses()->exists();

        return $user->addresses()->create(array_merge($data, [
            'is_default' => $isFirst,
        ]));
    }

    public function update(Address $address, array $data): Address
    {
        $address->update($data);

        return $address->fresh();
    }

    public function delete(Address $address): void
    {
        $address->delete();

        if ($address->is_default) {
            $next = Address::where('user_id', $address->user_id)->latest()->first();
            $next?->update(['is_default' => true]);
        }
    }

    public function setDefault(Address $address): Address
    {
        DB::transaction(function () use ($address): void {
            Address::where('user_id', $address->user_id)->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return $address->fresh();
    }
}
