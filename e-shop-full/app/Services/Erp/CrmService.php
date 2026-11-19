<?php

namespace App\Services\Erp;

use App\Enums\LeadStage;
use App\Models\CrmLead;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * CRM application: opportunities moving through a pipeline, weighted forecast,
 * and won leads turned into customers of the shop.
 */
class CrmService
{
    public function __construct(
        private ActivityLogger $activity,
        private EventOutbox $outbox,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Shop $shop, array $data): CrmLead
    {
        $stage = LeadStage::tryFrom($data['stage'] ?? '') ?? LeadStage::New;

        $lead = $shop->leads()->create([
            ...$data,
            'stage' => $stage,
            'probability' => $data['probability'] ?? $stage->defaultProbability(),
        ]);

        $this->activity->log($shop, 'lead.created', "Opportunité « {$lead->title} » créée.", $lead);

        return $lead;
    }

    /**
     * Move a lead to another stage of the pipeline (drag & drop on the board).
     */
    public function moveTo(CrmLead $lead, LeadStage $stage, ?string $lostReason = null): CrmLead
    {
        if ($stage === LeadStage::Lost && blank($lostReason)) {
            throw ValidationException::withMessages(['lost_reason' => 'Indiquez pourquoi l’opportunité est perdue : cela améliore vos prochaines ventes.']);
        }

        return DB::transaction(function () use ($lead, $stage, $lostReason): CrmLead {
            $previous = $lead->stage;

            $lead->update([
                'stage' => $stage,
                'probability' => $stage->defaultProbability(),
                'lost_reason' => $stage === LeadStage::Lost ? $lostReason : null,
                'closed_at' => $stage->isClosed() ? now() : null,
            ]);

            if ($stage === LeadStage::Won) {
                $this->convertToCustomer($lead);
                $this->outbox->record('lead.won', [
                    'lead_id' => $lead->id,
                    'title' => $lead->title,
                    'expected_revenue' => (float) $lead->expected_revenue,
                    'customer_id' => $lead->customer_id,
                ], $lead->shop);
            }

            $this->activity->log($lead->shop, 'lead.stage_changed', "« {$lead->title} » : {$previous->label()} → {$stage->label()}.", $lead, ['from' => $previous->value, 'to' => $stage->value]);

            return $lead;
        });
    }

    /**
     * Count, amount and weighted forecast for each stage of the pipeline.
     *
     * @return array{stages: list<array{stage: string, label: string, count: int, expected_revenue: float, weighted_revenue: float}>, open_count: int, weighted_forecast: float, win_rate: ?float}
     */
    public function pipeline(Shop $shop): array
    {
        $leads = $shop->leads()->get(['stage', 'expected_revenue', 'probability']);
        $stages = [];

        foreach (LeadStage::cases() as $stage) {
            $inStage = $leads->where('stage', $stage);

            $stages[] = [
                'stage' => $stage->value,
                'label' => $stage->label(),
                'count' => $inStage->count(),
                'expected_revenue' => round((float) $inStage->sum('expected_revenue'), 2),
                'weighted_revenue' => round($inStage->sum(fn (CrmLead $lead): float => $lead->weightedRevenue()), 2),
            ];
        }

        $open = $leads->reject(fn (CrmLead $lead): bool => $lead->stage->isClosed());
        $won = $leads->where('stage', LeadStage::Won)->count();
        $closed = $won + $leads->where('stage', LeadStage::Lost)->count();

        return [
            'stages' => $stages,
            'open_count' => $open->count(),
            'weighted_forecast' => round($open->sum(fn (CrmLead $lead): float => $lead->weightedRevenue()), 2),
            'win_rate' => $closed > 0 ? round($won / $closed * 100, 1) : null,
        ];
    }

    private function convertToCustomer(CrmLead $lead): void
    {
        if ($lead->customer_id !== null || blank($lead->email)) {
            return;
        }

        [$firstName, $lastName] = array_pad(explode(' ', trim($lead->contact_name), 2), 2, '');

        $customer = $lead->shop->customers()->firstOrCreate(
            ['email' => Str::lower($lead->email)],
            ['first_name' => $firstName, 'last_name' => $lastName, 'phone' => $lead->phone],
        );

        $lead->update(['customer_id' => $customer->id]);
    }
}
