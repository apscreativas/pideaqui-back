<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Rules\ValidSlug;
use App\Services\SlugSuggester;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Public endpoint consumed by self-signup and SuperAdmin onboarding screens
 * to check slug availability in real time.
 *
 *  GET /api/slug-check?slug=el-puebla
 *    → 200 { "available": true, "slug": "el-puebla" }
 *    → 200 { "available": false, "reason": "taken", "suggestions": [...] }
 *    → 200 { "available": false, "reason": "reserved", "suggestions": [...] }
 *    → 200 { "available": false, "reason": "invalid_format", "message": "..." }
 */
class SlugCheckController extends Controller
{
    public function check(Request $request, SlugSuggester $suggester): JsonResponse
    {
        $raw = (string) $request->query('slug', '');
        $slug = strtolower(trim($raw));

        if ($slug === '') {
            return response()->json([
                'available' => false,
                'reason' => 'invalid_format',
                'message' => 'El slug es obligatorio.',
            ]);
        }

        $validator = Validator::make(
            ['slug' => $slug],
            ['slug' => [new ValidSlug]],
        );

        if ($validator->fails()) {
            $firstError = $validator->errors()->first('slug');
            $isReserved = str_contains((string) $firstError, 'reservado');

            return response()->json([
                'available' => false,
                'reason' => $isReserved ? 'reserved' : 'invalid_format',
                'message' => $firstError,
                'suggestions' => $isReserved ? $suggester->suggest($slug) : [],
            ]);
        }

        if ($suggester->isTaken($slug)) {
            return response()->json([
                'available' => false,
                'reason' => 'taken',
                'message' => 'Ese slug ya está en uso.',
                'suggestions' => $suggester->suggest($slug),
            ]);
        }

        return response()->json([
            'available' => true,
            'slug' => $slug,
        ]);
    }
}
