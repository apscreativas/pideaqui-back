<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BranchController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Restaurant $restaurant */
        $restaurant = $request->attributes->get('restaurant');

        $branches = Branch::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->with('schedules')
            ->orderBy('name')
            ->get();

        return BranchResource::collection($branches);
    }
}
