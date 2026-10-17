<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A platform-wide setting overridden by an administrator. Defaults live in config/shopai.php.
 */
class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'json',
    ];
}
