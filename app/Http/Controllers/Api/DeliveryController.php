<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryCalculateRequest;
use App\Http\Resources\DeliveryCalculationResource;
use App\Models\Restaurant;
use App\Services\DeliveryService;
use Illuminate\Http\JsonResponse;

class DeliveryController extends Controller
{
    public function __construct(private readonly DeliveryService $deliveryService) {}

    public function calculate(DeliveryCalculateRequest $request): JsonResponse
    {
        /** @var Restaurant $restaurant */
        $restaurant = $request->attributes->get('restaurant');

        try {
            $result = $this->deliveryService->calculate(
                clientLat: (float) $request->validated('latitude'),
                clientLng: (float) $request->validated('longitude'),
                restaurant: $restaurant,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new DeliveryCalculationResource($result))->response();
    }
}
