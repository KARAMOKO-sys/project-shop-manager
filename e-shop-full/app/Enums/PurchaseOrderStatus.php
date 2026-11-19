<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    /**
     * Human-readable French label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Confirmed => 'Confirmé',
            self::PartiallyReceived => 'Partiellement reçu',
            self::Received => 'Reçu',
            self::Cancelled => 'Annulé',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function canReceive(): bool
    {
        return in_array($this, [self::Confirmed, self::PartiallyReceived], true);
    }
}
