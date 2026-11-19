<?php

namespace App\Http\Resources\Erp;

use App\Models\CrmLead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CrmLead
 */
class CrmLeadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'contact_name' => $this->contact_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'stage' => $this->stage,
            'stage_label' => $this->stage->label(),
            'source' => $this->source,
            'source_label' => $this->source->label(),
            'expected_revenue' => (float) $this->expected_revenue,
            'probability' => $this->probability,
            'weighted_revenue' => $this->weightedRevenue(),
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'notes' => $this->notes,
            'lost_reason' => $this->lost_reason,
            'closed_at' => $this->closed_at,
            'customer_id' => $this->customer_id,
            'created_at' => $this->created_at,
        ];
    }
}
