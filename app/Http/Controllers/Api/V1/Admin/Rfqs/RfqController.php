<?php

namespace App\Http\Controllers\Api\V1\Admin\Rfqs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Rfqs\IndexRfqRequest;
use App\Http\Requests\Api\V1\Admin\Rfqs\SendRfqSupplyNoticeRequest;
use App\Http\Requests\Api\V1\Admin\Rfqs\StoreRfqNoteRequest;
use App\Http\Requests\Api\V1\Admin\Rfqs\UpdateRfqRequest;
use App\Http\Resources\Api\V1\Admin\Rfqs\RfqCollection;
use App\Http\Resources\Api\V1\Admin\Rfqs\RfqNoteResource;
use App\Http\Resources\Api\V1\Admin\Rfqs\RfqResource;
use App\Models\Rfq;
use App\Services\RfqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RfqController extends Controller
{
    public function __construct(private readonly RfqService $rfqs) {}

    public function index(IndexRfqRequest $request): RfqCollection
    {
        return new RfqCollection($this->rfqs->paginate($request->validated()));
    }

    public function show(Rfq $rfq): RfqResource
    {
        Gate::authorize('view', $rfq);

        return new RfqResource($this->rfqs->findOrFail($rfq->id));
    }

    public function update(UpdateRfqRequest $request, Rfq $rfq): RfqResource
    {
        return new RfqResource($this->rfqs->updateStatus($rfq, $request->validated('status')));
    }

    public function storeNote(StoreRfqNoteRequest $request, Rfq $rfq): JsonResponse
    {
        return (new RfqNoteResource($this->rfqs->addNote(
            $rfq,
            $request->user(),
            $request->validated('body'),
        )))->response()->setStatusCode(201);
    }

    public function sendSupplyNotice(SendRfqSupplyNoticeRequest $request, Rfq $rfq): RfqResource
    {
        $result = $this->rfqs->sendSupplyNotice($rfq, $request->validated());

        return new RfqResource($result['rfq'], $result['email_sent']);
    }
}
