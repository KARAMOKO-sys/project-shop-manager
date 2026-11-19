<?php

namespace App\Models;

use App\Enums\LeadSource;
use App\Enums\LeadStage;
use Database\Factories\CrmLeadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmLead extends Model
{
    /** @use HasFactory<CrmLeadFactory> */
    use HasFactory;

    protected $fillable = ['shop_id', 'customer_id', 'title', 'contact_name', 'email', 'phone', 'stage', 'source', 'expected_revenue', 'probability', 'expected_close_date', 'notes', 'lost_reason', 'closed_at'];

    protected $casts = [
        'stage' => LeadStage::class,
        'source' => LeadSource::class,
        'expected_revenue' => 'decimal:2',
        'expected_close_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function weightedRevenue(): float
    {
        return round((float) $this->expected_revenue * $this->probability / 100, 2);
    }
}
