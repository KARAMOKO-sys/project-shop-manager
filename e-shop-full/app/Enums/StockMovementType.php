<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Opening = 'opening';
    case Receipt = 'receipt';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case Adjustment = 'adjustment';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';

    /**
     * Human-readable French label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Stock d’ouverture',
            self::Receipt => 'Réception fournisseur',
            self::Sale => 'Vente',
            self::SaleReturn => 'Retour / annulation',
            self::Adjustment => 'Inventaire / ajustement',
            self::TransferIn => 'Transfert entrant',
            self::TransferOut => 'Transfert sortant',
        };
    }
}
