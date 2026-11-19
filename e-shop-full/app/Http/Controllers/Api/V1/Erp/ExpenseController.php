<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Enums\ExpenseCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Erp\StoreExpenseRequest;
use App\Http\Resources\Erp\ExpenseResource;
use App\Models\Expense;
use App\Models\Shop;
use App\Services\Erp\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function __construct(private ActivityLogger $activity) {}

    public function index(Request $request, Shop $shop): AnonymousResourceCollection
    {
        $request->validate([
            'category' => ['nullable', Rule::enum(ExpenseCategory::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', Rule::when($request->filled('from'), ['after_or_equal:from'])],
        ]);

        $query = $shop->expenses()
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->query('category')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('spent_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('spent_at', '<=', $request->query('to')));

        $byCategory = (clone $query)->groupBy('category')->selectRaw('category, SUM(amount) as total')->pluck('total', 'category')
            ->map(fn ($sum): float => round((float) $sum, 2));

        return ExpenseResource::collection(
            (clone $query)->with('supplier:id,name')->orderByDesc('spent_at')->orderByDesc('id')->paginate(25),
        )->additional([
            'summary' => [
                'total' => round((float) $byCategory->sum(), 2),
                'by_category' => (object) $byCategory->all(),
            ],
        ]);
    }

    public function store(StoreExpenseRequest $request, Shop $shop): JsonResponse
    {
        $expense = $shop->expenses()->create([
            ...$request->validated(),
            'currency' => $shop->currency,
            'created_by' => $request->user()->id,
        ]);

        $this->activity->log($shop, 'expense.created', "Dépense « {$expense->label} » enregistrée.", $expense, ['amount' => (float) $expense->amount]);

        return (new ExpenseResource($expense->load('supplier:id,name')))->response()->setStatusCode(201);
    }

    public function update(StoreExpenseRequest $request, Shop $shop, Expense $expense): ExpenseResource
    {
        $expense->update($request->validated());

        return new ExpenseResource($expense->load('supplier:id,name'));
    }

    public function destroy(Shop $shop, Expense $expense): Response
    {
        $this->activity->log($shop, 'expense.deleted', "Dépense « {$expense->label} » supprimée.", properties: ['amount' => (float) $expense->amount]);
        $expense->delete();

        return response()->noContent();
    }
}
