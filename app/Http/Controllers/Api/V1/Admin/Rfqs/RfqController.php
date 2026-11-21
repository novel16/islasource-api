<?php

namespace App\Http\Controllers\Api\V1\Admin\Rfqs;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Rfqs\IndexRfqRequest;
use App\Http\Resources\Api\V1\Admin\Rfqs\RfqCollection;
use App\Http\Resources\Api\V1\Admin\Rfqs\RfqResource;
use App\Models\Rfq;
use App\Services\RfqService;
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
}
