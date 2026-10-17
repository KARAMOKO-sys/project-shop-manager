<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CashOnDelivery = 'cash_on_delivery';
    case MobileMoney = 'mobile_money';
    case Card = 'card';
    case Flutterwave = 'flutterwave';

    /**
     * Human-readable French label.
     */
    public function label(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'paiement à la livraison',
            self::MobileMoney => 'Mobile Money',
            self::Card => 'carte bancaire',
            self::Flutterwave => 'paiement en ligne',
        };
    }
}
