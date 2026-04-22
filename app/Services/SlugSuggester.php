<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\Scopes\TenantScope;
use Illuminate\Support\Str;

/**
 * Central slug helpers shared by self-signup, SuperAdmin onboarding, and
 * the live slug-check endpoint. Keeps all business rules (auto-generation
 * from a name, alternative suggestions when taken, reserved/format checks)
 * in one place to prevent divergence across entry points.
 */
class SlugSuggester
{
    /**
     * Sanitizes any input (user text, raw slug) into a candidate slug.
     * Returns an empty string if the input produces no valid characters.
     */
    public function sanitize(string $input): string
    {
        $slug = Str::slug($input, '-');

        return preg_replace('/-{2,}/', '-', trim($slug, '-')) ?? '';
    }

    /**
     * Builds a slug from a restaurant name and appends numeric suffixes
     * until it is globally unique in the `restaurants` table.
     */
    public function generateUnique(string $baseName): string
    {
        $base = $this->sanitize($baseName);
        if ($base === '' || strlen($base) < (int) config('tenants.slug_min_length', 3)) {
            $base = 'restaurante';
        }

        $candidate = $base;
        $suffix = 2;

        while ($this->isTaken($candidate) || $this->isReserved($candidate)) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Suggests up to `$count` available alternatives for a taken or invalid
     * slug. Uses numeric suffixes first, then falls back to `-mx` variants.
     *
     * @return array<int, string>
     */
    public function suggest(string $desired, int $count = 3): array
    {
        $base = $this->sanitize($desired);
        if ($base === '') {
            $base = 'restaurante';
        }

        $suggestions = [];
        $suffix = 2;

        while (count($suggestions) < $count && $suffix < 200) {
            $candidate = $base.'-'.$suffix;
            if (! $this->isTaken($candidate) && ! $this->isReserved($candidate)) {
                $suggestions[] = $candidate;
            }
            $suffix++;
        }

        return $suggestions;
    }

    public function isTaken(string $slug): bool
    {
        return Restaurant::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('slug', $slug)
            ->exists();
    }

    public function isReserved(string $slug): bool
    {
        $reserved = array_map('strtolower', (array) config('tenants.reserved_slugs', []));

        return in_array(strtolower($slug), $reserved, true);
    }
}
