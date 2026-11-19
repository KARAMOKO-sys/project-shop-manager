<?php

namespace App\Enums;

enum LeadStage: string
{
    case New = 'new';
    case Qualified = 'qualified';
    case Proposal = 'proposal';
    case Won = 'won';
    case Lost = 'lost';

    /**
     * Human-readable French label.
     */
    public function label(): string
    {
        return match ($this) {
            self::New => 'Nouveau',
            self::Qualified => 'Qualifié',
            self::Proposal => 'Proposition',
            self::Won => 'Gagné',
            self::Lost => 'Perdu',
        };
    }

    /**
     * Probability applied when a lead enters the stage, used for the weighted forecast.
     */
    public function defaultProbability(): int
    {
        return match ($this) {
            self::New => 10,
            self::Qualified => 30,
            self::Proposal => 60,
            self::Won => 100,
            self::Lost => 0,
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Won, self::Lost], true);
    }
}
