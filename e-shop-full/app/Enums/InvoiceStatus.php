<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    /**
     * Human-readable French label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Posted => 'Validée',
            self::PartiallyPaid => 'Partiellement payée',
            self::Paid => 'Payée',
            self::Cancelled => 'Annulée',
        };
    }

    /**
     * A validated invoice still waiting for (part of) its payment.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Posted, self::PartiallyPaid], true);
    }
}
