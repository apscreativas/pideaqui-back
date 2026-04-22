<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a value is a well-formed slug for a public restaurant URL.
 *
 * Checks format, length and reserved word collisions. Does NOT check
 * uniqueness — callers must pair this rule with `Rule::unique(...)` when
 * the slug must be globally unique in the `restaurants` table.
 */
class ValidSlug implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('El slug debe ser texto.');

            return;
        }

        $min = (int) config('tenants.slug_min_length', 3);
        $max = (int) config('tenants.slug_max_length', 50);

        $length = strlen($value);
        if ($length < $min) {
            $fail("El slug debe tener al menos {$min} caracteres.");

            return;
        }
        if ($length > $max) {
            $fail("El slug no puede exceder {$max} caracteres.");

            return;
        }

        $regex = config('tenants.slug_regex', '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/');
        if (! preg_match($regex, $value)) {
            $fail('El slug solo acepta minúsculas, números y guiones. Debe iniciar y terminar con letra o número.');

            return;
        }

        if (str_contains($value, '--')) {
            $fail('El slug no puede contener dos guiones seguidos.');

            return;
        }

        $reserved = array_map('strtolower', (array) config('tenants.reserved_slugs', []));
        if (in_array(strtolower($value), $reserved, true)) {
            $fail('Ese slug está reservado por la plataforma. Elige otro.');
        }
    }
}
