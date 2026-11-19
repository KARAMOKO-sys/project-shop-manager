<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\CrmLead;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Business history of the shop ("chatter"), optionally for one record.
 */
class ActivityController extends Controller
{
    /**
     * Record types that may be filtered on, by public name.
     *
     * @var array<string, string>
     */
    private const SUBJECTS = [
        'purchase_order' => PurchaseOrder::class,
        'invoice' => Invoice::class,
        'lead' => CrmLead::class,
        'product' => Product::class,
        'order' => Order::class,
    ];

    public function index(Request $request, Shop $shop): JsonResponse
    {
        $request->validate([
            'subject_type' => ['nullable', 'in:'.implode(',', array_keys(self::SUBJECTS))],
            'subject_id' => ['nullable', 'integer', 'required_with:subject_type'],
        ]);

        $activities = $shop->activities()
            ->with('user:id,name')
            ->when($request->filled('subject_type'), fn ($query) => $query
                ->where('subject_type', (new (self::SUBJECTS[$request->query('subject_type')]))->getMorphClass())
                ->where('subject_id', $request->integer('subject_id')))
            ->latest('id')
            ->paginate(30);

        return response()->json([
            'data' => $activities->getCollection()->map(fn (ActivityLog $activity): array => [
                'id' => $activity->id,
                'event' => $activity->event,
                'description' => $activity->description,
                'properties' => $activity->properties ?? (object) [],
                'user' => $activity->user?->name,
                'created_at' => $activity->created_at,
            ]),
            'meta' => ['current_page' => $activities->currentPage(), 'last_page' => $activities->lastPage(), 'total' => $activities->total()],
        ]);
    }
}
