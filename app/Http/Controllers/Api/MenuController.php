<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuCategoryResource;
use App\Models\Category;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MenuController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Restaurant $restaurant */
        $restaurant = $request->attributes->get('restaurant');

        $categories = Category::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->with([
                'products' => function ($query): void {
                    $query->where('is_active', true)
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->with([
                            'modifierGroups' => function ($q): void {
                                $q->orderBy('modifier_groups.sort_order')->with('options');
                            },
                        ]);
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return MenuCategoryResource::collection($categories);
    }
}
