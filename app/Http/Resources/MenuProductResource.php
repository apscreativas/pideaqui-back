<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MenuProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => (float) $product->price,
            'image_url' => $product->image_path
                ? Storage::disk(config('filesystems.media_disk', 'public'))->url($product->image_path)
                : null,
            'modifier_groups' => $product->getAllModifierGroups(),
        ];
        // NOTE: production_cost is intentionally excluded from this public API resource.
    }
}
