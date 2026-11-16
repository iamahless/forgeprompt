<?php

namespace App\Http\Controllers;

use App\Actions\Credits\PurchaseCredits;
use App\Billing\Usd;
use App\Http\Requests\StoreCreditPurchaseRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CreditPurchaseController extends Controller
{
    /**
     * Buy a pack of credits.
     */
    public function store(StoreCreditPurchaseRequest $request, PurchaseCredits $purchaseCredits): RedirectResponse
    {
        $purchase = $purchaseCredits->handle($request->user(), $request->integer('amount'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => Usd::format($purchase->amount_nanos).' in credits added.',
        ]);

        return back();
    }
}
