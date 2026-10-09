<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Received = 'received';
    case Paid = 'paid';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * Human-readable French label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Received => 'reçue',
            self::Paid => 'payée',
            self::Processing => 'en préparation',
            self::Shipped => 'expédiée',
            self::Delivered => 'livrée',
            self::Cancelled => 'annulée',
        };
    }

    /**
     * Statuses this order may move to from the current one.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Received => [self::Paid, self::Processing, self::Cancelled],
            self::Paid => [self::Processing, self::Cancelled],
            self::Processing => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }
}
