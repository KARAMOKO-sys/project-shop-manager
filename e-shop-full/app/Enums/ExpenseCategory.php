<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Rent = 'rent';
    case Salaries = 'salaries';
    case Transport = 'transport';
    case Marketing = 'marketing';
    case Utilities = 'utilities';
    case Supplies = 'supplies';
    case Taxes = 'taxes';
    case Fees = 'fees';
    case Other = 'other';

    /**
     * Human-readable French label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Rent => 'Loyer',
            self::Salaries => 'Salaires',
            self::Transport => 'Transport & livraison',
            self::Marketing => 'Marketing & publicité',
            self::Utilities => 'Électricité, eau, internet',
            self::Supplies => 'Fournitures & emballages',
            self::Taxes => 'Impôts & taxes',
            self::Fees => 'Frais bancaires & Mobile Money',
            self::Other => 'Autre',
        };
    }
}
