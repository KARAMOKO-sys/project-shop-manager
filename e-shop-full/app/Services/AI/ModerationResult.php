<?php

namespace App\Services\AI;

use App\Enums\ModerationStatus;
use App\Services\PlatformSettings;

final readonly class ModerationResult
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public int $riskScore,
        public array $reasons,
        public string $engine,
    ) {}

    public function status(): ModerationStatus
    {
        $settings = app(PlatformSettings::class);

        return match (true) {
            $this->riskScore >= $settings->get('moderation.reject_threshold') => ModerationStatus::Rejected,
            $this->riskScore >= $settings->get('moderation.review_threshold') => ModerationStatus::PendingReview,
            default => ModerationStatus::Approved,
        };
    }

    /**
     * Keep the riskier verdict of two engines, combining their explanations.
     */
    public function mergeWith(self $other): self
    {
        return new self(
            riskScore: max($this->riskScore, $other->riskScore),
            reasons: array_values(array_unique([...$this->reasons, ...$other->reasons])),
            engine: $other->riskScore > $this->riskScore ? $other->engine : $this->engine,
        );
    }
}
