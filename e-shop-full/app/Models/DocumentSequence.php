<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Gap-free counter per shop and document type (invoices, purchase orders…).
 */
class DocumentSequence extends Model
{
    protected $fillable = ['shop_id', 'key', 'next_number'];
}
