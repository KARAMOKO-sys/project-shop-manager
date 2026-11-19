<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An ERP application installed on a shop (see config/erp.php).
 */
class ShopModule extends Model
{
    protected $fillable = ['shop_id', 'module', 'settings', 'installed_by', 'installed_at'];

    protected $casts = [
        'settings' => 'array',
        'installed_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by');
    }
}
