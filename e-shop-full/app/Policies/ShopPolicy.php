<?php

namespace App\Policies;

use App\Models\Shop;
use App\Models\User;

class ShopPolicy
{
    /**
     * Only the owner may manage a shop and everything it contains.
     */
    public function manage(User $user, Shop $shop): bool
    {
        return $shop->user_id === $user->id;
    }
}
