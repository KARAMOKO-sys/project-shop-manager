<?php

namespace App\Enums;

enum DeliveryMethod: string
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';

    /**
     * Human-readable French label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Delivery => 'livraison à domicile',
            self::Pickup => 'retrait en boutique',
        };
    }
}
