<?php

namespace App\Http\Controllers\Api\V1\Erp;

use App\Enums\LeadStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Erp\MoveLeadStageRequest;
use App\Http\Requests\Erp\StoreCrmLeadRequest;
use App\Http\Resources\Erp\CrmLeadResource;
use App\Models\CrmLead;
use App\Models\Shop;
use App\Services\Erp\CrmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class CrmLeadController extends Controller
{
    public function __construct(private CrmService $crm) {}

    /**
     * Every lead of the board, not paginated: the pipeline is shown as columns.
     */
    public function index(Request $request, Shop $shop): AnonymousResourceCollection
    {
        $request->validate([
            'stage' => ['nullable', Rule::enum(LeadStage::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = $request->query('search');

        return CrmLeadResource::collection(
            $shop->leads()
                ->when($request->filled('stage'), fn ($query) => $query->where('stage', $request->query('stage')))
                ->when(filled($search), fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")))
                ->orderByRaw('expected_close_date IS NULL')
                ->orderBy('expected_close_date')
                ->orderBy('id')
                ->get(),
        );
    }

    public function store(StoreCrmLeadRequest $request, Shop $shop): JsonResponse
    {
        $data = $request->validated();
        $data['expected_revenue'] ??= 0;

        return (new CrmLeadResource($this->crm->create($shop, $data)))->response()->setStatusCode(201);
    }

    public function update(StoreCrmLeadRequest $request, Shop $shop, CrmLead $lead): CrmLeadResource
    {
        $lead->update($request->validated());

        return new CrmLeadResource($lead);
    }

    public function destroy(Shop $shop, CrmLead $lead): Response
    {
        $lead->delete();

        return response()->noContent();
    }

    public function pipeline(Shop $shop): JsonResponse
    {
        return response()->json(['data' => $this->crm->pipeline($shop)]);
    }

    public function moveStage(MoveLeadStageRequest $request, Shop $shop, CrmLead $lead): CrmLeadResource
    {
        $lead = $this->crm->moveTo($lead, LeadStage::from($request->validated('stage')), $request->validated('lost_reason'));

        return new CrmLeadResource($lead);
    }
}
