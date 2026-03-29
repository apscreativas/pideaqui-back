<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuCategoryResource;
use App\Models\Category;
use App\Models\Promotion;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

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
                                $q->where('is_active', true)
                                    ->orderBy('modifier_groups.sort_order')
                                    ->with(['options' => fn ($oq) => $oq->where('is_active', true)]);
                            },
                            'modifierGroupTemplates' => function ($q): void {
                                $q->where('is_active', true)
                                    ->with(['options' => fn ($oq) => $oq->where('is_active', true)]);
                            },
                        ]);
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Filter categories by availability schedule.
        $categories = $categories->filter(fn (Category $cat) => $cat->isCurrentlyAvailable());

        // Build virtual "Promociones" category from standalone promotions.
        $promotionCategory = $this->buildPromotionCategory($restaurant);

        $result = MenuCategoryResource::collection($categories);

        if ($promotionCategory) {
            $result = collect([$promotionCategory])->merge($result);
        }

        return MenuCategoryResource::collection($result);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildPromotionCategory(Restaurant $restaurant): ?array
    {
        $promotions = Promotion::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('is_active', true)
            ->with([
                'modifierGroups' => function ($q): void {
                    $q->where('is_active', true)
                        ->orderBy('sort_order')
                        ->with(['options' => fn ($oq) => $oq->where('is_active', true)]);
                },
                'modifierGroupTemplates' => function ($q): void {
                    $q->where('is_active', true)
                        ->with(['options' => fn ($oq) => $oq->where('is_active', true)]);
                },
            ])
            ->orderBy('sort_order')
            ->get();

        $activePromotions = $promotions->filter(fn (Promotion $p) => $p->isCurrentlyActive());

        if ($activePromotions->isEmpty()) {
            return null;
        }

        $mediaDisk = config('filesystems.media_disk', 'public');

        $products = $activePromotions->values()->map(fn (Promotion $promo) => [
            'id' => 'promo_'.$promo->id,
            'promotion_id' => $promo->id,
            'name' => $promo->name,
            'description' => $promo->description,
            'price' => (float) $promo->price,
            'image_url' => $promo->image_path
                ? Storage::disk($mediaDisk)->url($promo->image_path)
                : null,
            'modifier_groups' => $promo->getAllModifierGroups(),
            'is_promotion' => true,
        ]);

        return [
            'id' => null,
            'name' => 'Promociones',
            'description' => null,
            'image_url' => null,
            'sort_order' => -1,
            'is_promotion_category' => true,
            'products' => $products,
        ];
    }
}
