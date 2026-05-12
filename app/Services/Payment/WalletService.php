<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\User;
use App\Models\WalletBalance;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function getOrCreateWallet(User $user): WalletBalance
    {
        return WalletBalance::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0, 'on_hold' => 0]
        );
    }

    public function getBalance(User $user): WalletBalance
    {
        return $this->getOrCreateWallet($user);
    }

    public function getTransactions(User $user, int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $wallet = $this->getOrCreateWallet($user);

        return $wallet->transactions()->latest()->paginate($perPage);
    }

    public function topUp(User $user, int $amount, string $referenceType, int $referenceId): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $referenceType, $referenceId) {
            $wallet = WalletBalance::where('user_id', $user->id)->lockForUpdate()->firstOrCreate(
                ['user_id' => $user->id],
                ['balance' => 0, 'on_hold' => 0]
            );

            $wallet->increment('balance', $amount);

            return WalletTransaction::create([
                'wallet_balance_id' => $wallet->id,
                'type'              => 'credit',
                'amount'            => $amount,
                'description'       => 'Wallet top-up',
                'reference_type'    => $referenceType,
                'reference_id'      => $referenceId,
            ]);
        });
    }

    public function creditMerchant(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $feePercent     = config('platform.fee_percent', 5.0);
            $fee            = (int) round($order->total * $feePercent / 100);
            $merchantCredit = $order->total - $fee;

            $merchant = $order->store->user;
            $wallet   = WalletBalance::where('user_id', $merchant->id)->lockForUpdate()->firstOrCreate(
                ['user_id' => $merchant->id],
                ['balance' => 0, 'on_hold' => 0]
            );

            $wallet->increment('balance', $merchantCredit);

            WalletTransaction::create([
                'wallet_balance_id' => $wallet->id,
                'type'              => 'credit',
                'amount'            => $merchantCredit,
                'description'       => "Order #{$order->order_number} completed (fee: {$feePercent}%)",
                'reference_type'    => Order::class,
                'reference_id'      => $order->id,
            ]);
        });
    }

    public function creditUser(User $user, int $amount, string $description, string $referenceType, int $referenceId): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $description, $referenceType, $referenceId) {
            $wallet = WalletBalance::where('user_id', $user->id)->lockForUpdate()->firstOrCreate(
                ['user_id' => $user->id],
                ['balance' => 0, 'on_hold' => 0]
            );

            $wallet->increment('balance', $amount);

            return WalletTransaction::create([
                'wallet_balance_id' => $wallet->id,
                'type'              => 'credit',
                'amount'            => $amount,
                'description'       => $description,
                'reference_type'    => $referenceType,
                'reference_id'      => $referenceId,
            ]);
        });
    }

    public function initiateWithdraw(User $user, int $amount): WalletBalance
    {
        return DB::transaction(function () use ($user, $amount) {
            $wallet = WalletBalance::where('user_id', $user->id)->lockForUpdate()->firstOrCreate(
                ['user_id' => $user->id],
                ['balance' => 0, 'on_hold' => 0]
            );

            if ($wallet->balance < $amount) {
                throw new \DomainException('Insufficient wallet balance.');
            }

            $wallet->decrement('balance', $amount);
            $wallet->increment('on_hold', $amount);

            WalletTransaction::create([
                'wallet_balance_id' => $wallet->id,
                'type'              => 'debit',
                'amount'            => $amount,
                'description'       => 'Withdrawal initiated',
                'reference_type'    => null,
                'reference_id'      => null,
            ]);

            return $wallet->fresh();
        });
    }

    public function confirmWithdraw(WalletBalance $wallet, int $amount, bool $success): void
    {
        DB::transaction(function () use ($wallet, $amount, $success) {
            $locked = WalletBalance::where('id', $wallet->id)->lockForUpdate()->firstOrFail();
            $locked->decrement('on_hold', $amount);

            if (! $success) {
                // Disbursement failed — roll back to balance
                $locked->increment('balance', $amount);

                WalletTransaction::create([
                    'wallet_balance_id' => $wallet->id,
                    'type'              => 'credit',
                    'amount'            => $amount,
                    'description'       => 'Withdrawal failed — balance restored',
                    'reference_type'    => null,
                    'reference_id'      => null,
                ]);
            }
        });
    }
}
