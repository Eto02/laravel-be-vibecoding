<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreAddressRequest;
use App\Http\Requests\User\UpdateAddressRequest;
use App\Http\Resources\User\AddressResource;
use App\Http\Responses\ApiResponse;
use App\Models\Address;
use App\Services\User\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AddressController extends Controller
{
    public function __construct(
        private readonly AddressService $addressService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $addresses = $this->addressService->list($request->user());

        return ApiResponse::success('Addresses retrieved.', AddressResource::collection($addresses));
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $address = $this->addressService->store($request->user(), $request->validated());

        return ApiResponse::success('Address created.', new AddressResource($address), 201);
    }

    public function show(Request $request, Address $address): JsonResponse
    {
        $this->authorize('view', $address);

        return ApiResponse::success('Address retrieved.', new AddressResource($address));
    }

    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        $this->authorize('update', $address);

        $address = $this->addressService->update($address, $request->validated());

        return ApiResponse::success('Address updated.', new AddressResource($address));
    }

    public function destroy(Request $request, Address $address): Response
    {
        $this->authorize('delete', $address);

        $this->addressService->delete($address);

        return response()->noContent();
    }

    public function setDefault(Request $request, Address $address): JsonResponse
    {
        $this->authorize('setDefault', $address);

        $address = $this->addressService->setDefault($address);

        return ApiResponse::success('Default address updated.', new AddressResource($address));
    }
}
