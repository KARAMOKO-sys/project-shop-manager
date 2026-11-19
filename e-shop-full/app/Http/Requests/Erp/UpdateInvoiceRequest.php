<?php

namespace App\Http\Requests\Erp;

/**
 * Same rules as creation, every field optional (draft invoices only).
 */
class UpdateInvoiceRequest extends StoreInvoiceRequest {}
