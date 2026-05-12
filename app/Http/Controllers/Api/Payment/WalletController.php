<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\WalletTopupRequest;
use App\Http\Requests\Payment\WalletWithdrawRequest;
use App\Http\Resources\Payment\WalletBalanceResource;
use App\Http\Resources\Payment\WalletTransactionResource;
use App\Http\Responses\ApiResponse;
use App\Services\Payment\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    public function balance(Request $request): JsonResponse
    {
        $wallet = $this->walletService->getBalance($request->user());

        return ApiResponse::success('Wallet balance retrieved.', new WalletBalanceResource($wallet));
    }

    public function transactions(Request $request): JsonResponse
    {
        $transactions = $this->walletService->getTransactions($request->user());

        return ApiResponse::success(
            'Wallet transactions retrieved.',
            WalletTransactionResource::collection($transactions),
            paginationMeta: [
                'current_page' => $transactions->currentPage(),
                'last_page'    => $transactions->lastPage(),
                'per_page'     => $transactions->perPage(),
                'total'        => $transactions->total(),
            ]
        );
    }

    public function topup(WalletTopupRequest $request): JsonResponse
    {
        // Top-up flow: initiate payment, on success webhook credit wallet
        // For simplicity, return a payment intent so frontend can pay
        // Actual credit happens via PaymentCaptured → CreditUserWallet listener
        return ApiResponse::success('Top-up payment initiated. Complete payment to credit wallet.', [
            'message' => 'Redirect user to initiate a payment via POST /api/payments/initiate with the wallet top-up amount.',
            'amount'  => $request->integer('amount'),
        ]);
    }

    public function withdraw(WalletWithdrawRequest $request): JsonResponse
    {
        $wallet = $this->walletService->initiateWithdraw(
            $request->user(),
            $request->integer('amount')
        );

        return ApiResponse::success('Withdrawal initiated. Funds will be transferred within 1-3 business days.', new WalletBalanceResource($wallet));
    }
}
