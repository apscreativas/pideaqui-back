<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Shared logic for syncing modifier groups on Product and Promotion models.
 * Both models must have a `modifierGroups()` HasMany relationship and a `restaurant_id` attribute.
 */
trait SyncsModifierGroups
{
    /**
     * @param  array<int, array<string, mixed>>  $groups
     */
    private function syncModifierGroups(Model $entity, array $groups): void
    {
        $existingGroupIds = $entity->modifierGroups()->pluck('id')->all();
        $incomingGroupIds = [];

        foreach ($groups as $sortOrder => $groupData) {
            if (! empty($groupData['id']) && in_array($groupData['id'], $existingGroupIds)) {
                $group = $entity->modifierGroups()->find($groupData['id']);
                $group->update([
                    'name' => $groupData['name'],
                    'selection_type' => $groupData['selection_type'],
                    'is_required' => $groupData['is_required'] ?? false,
                    'is_active' => $groupData['is_active'] ?? true,
                    'max_selections' => ($groupData['selection_type'] ?? 'single') === 'multiple' ? ($groupData['max_selections'] ?? null) : null,
                    'sort_order' => $sortOrder,
                ]);
                $incomingGroupIds[] = $group->id;

                $existingOptionIds = $group->options()->pluck('id')->all();
                $incomingOptionIds = [];

                foreach ($groupData['options'] as $optSort => $optData) {
                    if (! empty($optData['id']) && in_array($optData['id'], $existingOptionIds)) {
                        $group->options()->where('id', $optData['id'])->update([
                            'name' => $optData['name'],
                            'price_adjustment' => $optData['price_adjustment'] ?? 0,
                            'production_cost' => $optData['production_cost'] ?? 0,
                            'is_active' => $optData['is_active'] ?? true,
                            'sort_order' => $optSort,
                        ]);
                        $incomingOptionIds[] = $optData['id'];
                    } else {
                        $newOpt = $group->options()->create([
                            'name' => $optData['name'],
                            'price_adjustment' => $optData['price_adjustment'] ?? 0,
                            'production_cost' => $optData['production_cost'] ?? 0,
                            'is_active' => $optData['is_active'] ?? true,
                            'sort_order' => $optSort,
                        ]);
                        $incomingOptionIds[] = $newOpt->id;
                    }
                }

                $group->options()->whereNotIn('id', $incomingOptionIds)->delete();
            } else {
                $group = $entity->modifierGroups()->create([
                    'restaurant_id' => $entity->restaurant_id,
                    'name' => $groupData['name'],
                    'selection_type' => $groupData['selection_type'],
                    'is_required' => $groupData['is_required'] ?? false,
                    'is_active' => $groupData['is_active'] ?? true,
                    'max_selections' => ($groupData['selection_type'] ?? 'single') === 'multiple' ? ($groupData['max_selections'] ?? null) : null,
                    'sort_order' => $sortOrder,
                ]);
                $incomingGroupIds[] = $group->id;

                foreach ($groupData['options'] as $optSort => $optData) {
                    $group->options()->create([
                        'name' => $optData['name'],
                        'price_adjustment' => $optData['price_adjustment'] ?? 0,
                        'production_cost' => $optData['production_cost'] ?? 0,
                        'sort_order' => $optSort,
                    ]);
                }
            }
        }

        $entity->modifierGroups()->whereNotIn('id', $incomingGroupIds)->delete();
    }
}
