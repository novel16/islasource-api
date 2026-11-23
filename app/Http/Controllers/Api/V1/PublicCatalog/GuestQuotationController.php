<?php

namespace App\Http\Controllers\Api\V1\PublicCatalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PublicCatalog\AcceptGuestQuotationRequest;
use App\Http\Requests\Api\V1\PublicCatalog\DeclineGuestQuotationRequest;
use App\Http\Resources\Api\V1\PublicCatalog\PublicQuotationResource;
use App\Services\QuotationService;

class GuestQuotationController extends Controller
{
    public function __construct(private readonly QuotationService $quotations) {}

    public function show(string $token): PublicQuotationResource
    {
        return new PublicQuotationResource($this->quotations->findForGuest($token));
    }

    public function accept(AcceptGuestQuotationRequest $request, string $token): PublicQuotationResource
    {
        return new PublicQuotationResource($this->quotations->acceptByGuest($token));
    }

    public function decline(DeclineGuestQuotationRequest $request, string $token): PublicQuotationResource
    {
        return new PublicQuotationResource($this->quotations->declineByGuest($token, $request->validated('reason')));
    }
}
