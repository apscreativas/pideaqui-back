<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderConfirmationResource;
use App\Models\Restaurant;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        /** @var Restaurant $restaurant */
        $restaurant = $request->attributes->get('restaurant');

        try {
            $result = $this->orderService->store($request->validated(), $restaurant);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => 'Lo sentimos, no podemos recibir más pedidos este mes.',
                'error' => $e->getMessage(),
            ], 422);
        } catch (ValidationException $e) {
            throw $e;
        }

        return (new OrderConfirmationResource($result))->response()->setStatusCode(201);
    }
}
