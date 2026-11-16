<?php

namespace App\Actions\Credits;

use App\Billing\Usd;
use App\Models\CreditPurchase;
use App\Models\User;

class PurchaseCredits
{
    /**
     * Add credits to the user's account. No payment is taken.
     */
    public function handle(User $user, int $dollars): CreditPurchase
    {
        return $user->creditPurchases()->create([
            'amount_nanos' => $dollars * Usd::NANOS_PER_DOLLAR,
        ]);
    }
}
