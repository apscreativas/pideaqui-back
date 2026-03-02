<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::with(['products.modifierGroups.options'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Menu/Index', [
            'categories' => $categories,
        ]);
    }
}
