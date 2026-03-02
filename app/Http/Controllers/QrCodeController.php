<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QrCodeController extends Controller
{
    public function index(Request $request): Response
    {
        $restaurant = $request->user()->load('restaurant')->restaurant;

        return Inertia::render('Settings/QrCode', [
            'access_token' => $restaurant->access_token,
            'restaurant_name' => $restaurant->name,
        ]);
    }
}
