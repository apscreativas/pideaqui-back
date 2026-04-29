<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

/**
 * Per-item uniqueness check for `items.*.modifiers.*.modifier_option_id`
 * and `items.*.modifiers.*.modifier_option_template_id`.
 *
 * Replaces Laravel's `distinct` rule, which when applied with two nested
 * wildcards (`items.*.modifiers.*.x`) flattens across all items and
 * incorrectly rejects orders where different items legitimately share
 * the same catalog template option (e.g. several burgers each with
 * "Lemon Pepper" linked from the modifier catalog).
 *
 * Form requests using this trait must call `validateItemModifierUniqueness`
 * from their `withValidator()` hook.
 */
trait ValidatesItemModifiers
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $this->validateItemModifierUniqueness($v);
        });
    }

    private function validateItemModifierUniqueness(Validator $validator): void
    {
        $items = (array) $this->input('items', []);

        foreach ($items as $i => $item) {
            $modifiers = $item['modifiers'] ?? [];
            if (! is_array($modifiers)) {
                continue;
            }

            $seenOptionIds = [];
            $seenTemplateIds = [];

            foreach ($modifiers as $j => $mod) {
                $optionId = $mod['modifier_option_id'] ?? null;
                if ($optionId !== null && $optionId !== '') {
                    if (in_array($optionId, $seenOptionIds, true)) {
                        $validator->errors()->add(
                            "items.{$i}.modifiers.{$j}.modifier_option_id",
                            'No puedes seleccionar la misma opción más de una vez en un mismo producto.'
                        );
                    } else {
                        $seenOptionIds[] = $optionId;
                    }
                }

                $templateId = $mod['modifier_option_template_id'] ?? null;
                if ($templateId !== null && $templateId !== '') {
                    if (in_array($templateId, $seenTemplateIds, true)) {
                        $validator->errors()->add(
                            "items.{$i}.modifiers.{$j}.modifier_option_template_id",
                            'No puedes seleccionar la misma opción más de una vez en un mismo producto.'
                        );
                    } else {
                        $seenTemplateIds[] = $templateId;
                    }
                }
            }
        }
    }
}
