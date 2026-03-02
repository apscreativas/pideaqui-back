<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePaymentMethodRequest;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PaymentMethod::class);

        return Inertia::render('Settings/PaymentMethods', [
            'payment_methods' => PaymentMethod::orderBy('type')->get(),
        ]);
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->authorize('update', $paymentMethod);

        $paymentMethod->update($request->validated());

        return back()->with('success', 'Método de pago actualizado.');
    }
}
