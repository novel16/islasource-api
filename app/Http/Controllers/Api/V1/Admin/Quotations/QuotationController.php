<?php

namespace App\Http\Controllers\Api\V1\Admin\Quotations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Quotations\CancelQuotationRequest;
use App\Http\Requests\Api\V1\Admin\Quotations\CreateQuotationRequest;
use App\Http\Requests\Api\V1\Admin\Quotations\IndexQuotationRequest;
use App\Http\Requests\Api\V1\Admin\Quotations\UpdateQuotationRequest;
use App\Http\Resources\Api\V1\Admin\Quotations\QuotationCollection;
use App\Http\Resources\Api\V1\Admin\Quotations\QuotationResource;
use App\Models\Quotation;
use App\Models\Rfq;
use App\Services\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class QuotationController extends Controller
{
    public function __construct(private readonly QuotationService $quotations) {}

    public function index(IndexQuotationRequest $request): QuotationCollection
    {
        return new QuotationCollection($this->quotations->paginate($request->validated()));
    }

    public function store(CreateQuotationRequest $request, Rfq $rfq): JsonResponse
    {
        $result = $this->quotations->createDraft($rfq, $request->user(), $request->validated());

        return (new QuotationResource($result['quotation']))
            ->response()
            ->setStatusCode($result['created'] ? 201 : 200);
    }

    public function show(Quotation $quotation): QuotationResource
    {
        Gate::authorize('view', $quotation);

        return new QuotationResource($this->quotations->findForAdmin($quotation));
    }

    public function update(UpdateQuotationRequest $request, Quotation $quotation): QuotationResource
    {
        return new QuotationResource($this->quotations->updateDraft($quotation, $request->validated()));
    }

    public function destroy(Quotation $quotation): Response
    {
        Gate::authorize('delete', $quotation);
        $this->quotations->discardDraft($quotation);

        return response()->noContent();
    }

    public function send(Quotation $quotation): QuotationResource
    {
        Gate::authorize('update', $quotation);
        $result = $this->quotations->send($quotation);

        return new QuotationResource($result['quotation'], $result['email_sent']);
    }

    public function resend(Quotation $quotation): QuotationResource
    {
        Gate::authorize('update', $quotation);
        $result = $this->quotations->resend($quotation);

        return new QuotationResource($result['quotation'], $result['email_sent']);
    }

    public function cancel(CancelQuotationRequest $request, Quotation $quotation): QuotationResource
    {
        return new QuotationResource($this->quotations->cancel($quotation, $request->validated('reason')));
    }

    public function revise(Request $request, Quotation $quotation): JsonResponse
    {
        Gate::authorize('create', Quotation::class);
        Gate::authorize('view', $quotation);
        $result = $this->quotations->revise($quotation, $request->user());

        return (new QuotationResource($result['quotation']))
            ->response()
            ->setStatusCode($result['created'] ? 201 : 200);
    }
}
