<?php

namespace App\Enums;

enum LeadSource: string
{
    case WhatsApp = 'whatsapp';
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Storefront = 'storefront';
    case Referral = 'referral';
    case WalkIn = 'walk_in';
    case Event = 'event';
    case Other = 'other';

    /**
     * Human-readable French label.
     */
    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::Storefront => 'Boutique en ligne',
            self::Referral => 'Recommandation',
            self::WalkIn => 'Visite en boutique',
            self::Event => 'Salon / événement',
            self::Other => 'Autre',
        };
    }
}
