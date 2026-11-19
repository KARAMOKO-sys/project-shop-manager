<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Services\Erp\ErpDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErpDashboardController extends Controller
{
    public function show(Request $request, Shop $shop, ErpDashboardService $dashboard): JsonResponse
    {
        $days = in_array((int) $request->query('days'), [7, 30, 90, 365], true) ? (int) $request->query('days') : 30;

        return response()->json(['data' => $dashboard->forShop($shop, $days)]);
    }
}
