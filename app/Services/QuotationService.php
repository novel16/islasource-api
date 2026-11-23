<?php

namespace App\Services;

use App\Mail\QuotationSentMail;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class QuotationService
{
    public function __construct(private readonly QuotationCalculator $calculator) {}

    public function shareUrl(Quotation $quotation): ?string
    {
        $token = $quotation->access_token_encrypted;

        if (! is_string($token) || $token === '') {
            return null;
        }

        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:3000'), '/');

        return "{$frontendUrl}/quote/{$token}";
    }

    public function expireOverdue(?int $rfqId = null): void
    {
        $query = Quotation::query()
            ->where('status', 'Sent')
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', now());

        if ($rfqId !== null) {
            $query->where('rfq_id', $rfqId);
        }

        $query->update(['status' => 'Expired']);
    }

    public function refreshExpiry(Quotation $quotation): Quotation
    {
        if ($quotation->status === 'Sent' && $quotation->valid_until !== null && now()->greaterThan($quotation->valid_until)) {
            Quotation::query()
                ->where('id', $quotation->id)
                ->where('status', 'Sent')
                ->where('valid_until', '<', now())
                ->update(['status' => 'Expired']);

            $quotation->refresh();
        }

        return $quotation;
    }

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, Quotation>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $this->expireOverdue();

        $query = Quotation::query()->with('rfq')->withCount('items');

        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }

        if ($filters['rfq_id'] ?? null) {
            $query->where('rfq_id', (int) $filters['rfq_id']);
        }

        $search = $filters['search'] ?? null;

        if ($search !== null && $search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('reference', '%'.$search.'%')
                    ->orWhereHas('rfq', function (Builder $rfq) use ($search): void {
                        $rfq->whereLike('reference', '%'.$search.'%')
                            ->orWhereLike('customer_name', '%'.$search.'%')
                            ->orWhereLike('customer_email', '%'.$search.'%');
                    });
            });
        }

        return $query->orderByDesc('id')->paginate($filters['per_page'] ?? 15)
            ->appends(array_filter($filters, fn (mixed $value): bool => $value !== null));
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array{quotation: Quotation, created: bool}
     */
    public function createDraft(Rfq $rfq, User $admin, array $defaults = []): array
    {
        return DB::transaction(function () use ($rfq, $admin, $defaults): array {
            $lockedRfq = Rfq::query()->with('items')->lockForUpdate()->findOrFail($rfq->id);
            $this->expireOverdue($lockedRfq->id);

            if ($lockedRfq->status === 'Closed') {
                throw ValidationException::withMessages([
                    'rfq' => 'Closed quote requests cannot receive new quotations.',
                ]);
            }

            if ($lockedRfq->quotations()->where('status', 'Accepted')->exists()) {
                throw ValidationException::withMessages([
                    'rfq' => 'This request already has an accepted quotation.',
                ]);
            }

            $existingDraft = $lockedRfq->quotations()->where('status', 'Draft')->first();

            if ($existingDraft !== null) {
                return [
                    'quotation' => $existingDraft->load(['rfq.quotations', 'items']),
                    'created' => false,
                ];
            }

            if ($lockedRfq->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'rfq' => 'This request has no items to quote.',
                ]);
            }

            $nextVersion = ((int) $lockedRfq->quotations()->max('version')) + 1;
            $depositPercent = isset($defaults['deposit_percent']) ? (int) $defaults['deposit_percent'] : Quotation::DEFAULT_DEPOSIT_PERCENT;
            $validityHours = isset($defaults['validity_hours']) ? (int) $defaults['validity_hours'] : Quotation::DEFAULT_VALIDITY_HOURS;
            $terms = isset($defaults['terms']) && trim((string) $defaults['terms']) !== ''
                ? trim((string) $defaults['terms'])
                : "{$depositPercent}% deposit confirms your order. Balance is due before dispatch. Availability is confirmed upon deposit.";

            $quotation = Quotation::query()->create([
                'rfq_id' => $lockedRfq->id,
                'reference' => 'QT-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
                'version' => $nextVersion,
                'status' => 'Draft',
                'seafood_cost_centavos' => 0,
                'handling_centavos' => 0,
                'markup_percent' => '20.00',
                'markup_centavos' => 0,
                'subtotal_centavos' => 0,
                'delivery_centavos' => 0,
                'total_centavos' => 0,
                'deposit_percent' => $depositPercent,
                'deposit_centavos' => 0,
                'balance_centavos' => 0,
                'validity_hours' => $validityHours,
                'terms' => $terms,
                'created_by' => $admin->id,
            ]);

            foreach ($lockedRfq->items as $rfqItem) {
                $quotation->items()->create([
                    'rfq_item_id' => $rfqItem->id,
                    'product_name' => $rfqItem->product_name,
                    'unit' => $rfqItem->unit,
                    'quantity' => $rfqItem->quantity,
                    'specs' => $rfqItem->customer_notes,
                    'is_available' => true,
                    'unit_cost_centavos' => 0,
                    'line_cost_centavos' => 0,
                    'line_selling_centavos' => 0,
                ]);
            }

            return [
                'quotation' => $quotation->refresh()->load(['rfq.quotations', 'items']),
                'created' => true,
            ];
        });
    }

    public function findForAdmin(Quotation $quotation): Quotation
    {
        $this->expireOverdue($quotation->rfq_id);
        $quotation->refresh();

        return $quotation->load(['rfq.quotations', 'items']);
    }

    /** @param array<string, mixed> $attributes */
    public function updateDraft(Quotation $quotation, array $attributes): Quotation
    {
        return DB::transaction(function () use ($quotation, $attributes): Quotation {
            $locked = Quotation::query()->with('items')->lockForUpdate()->findOrFail($quotation->id);

            if ($locked->status !== 'Draft') {
                throw ValidationException::withMessages([
                    'status' => 'Sent or finalized quotations cannot be edited. Create a revision instead.',
                ]);
            }

            $existingItems = $locked->items->keyBy('id');
            $incomingItems = $attributes['items'];

            if (count($incomingItems) !== $existingItems->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Every requested item on this quotation must be included.',
                ]);
            }

            $seenIds = [];
            $calcInput = [];

            foreach ($incomingItems as $index => $row) {
                $itemId = (int) $row['id'];

                if (! $existingItems->has($itemId) || isset($seenIds[$itemId])) {
                    throw ValidationException::withMessages([
                        "items.{$index}.id" => 'Each item must reference a unique line belonging to this quotation.',
                    ]);
                }

                $seenIds[$itemId] = true;
                $calcInput[] = [
                    'quantity' => $row['quantity'],
                    'unit_cost_centavos' => (int) $row['unit_cost_centavos'],
                    'is_available' => (bool) $row['is_available'],
                ];
            }

            $totals = $this->calculator->calculate(
                items: $calcInput,
                handlingCentavos: (int) $attributes['handling_centavos'],
                markupPercent: $attributes['markup_percent'],
                deliveryCentavos: (int) $attributes['delivery_centavos'],
                depositPercent: (int) $attributes['deposit_percent'],
            );

            $locked->fill([
                'seafood_cost_centavos' => $totals['seafood_cost_centavos'],
                'handling_centavos' => $totals['handling_centavos'],
                'markup_percent' => number_format((float) $attributes['markup_percent'], 2, '.', ''),
                'markup_centavos' => $totals['markup_centavos'],
                'subtotal_centavos' => $totals['subtotal_centavos'],
                'delivery_centavos' => $totals['delivery_centavos'],
                'total_centavos' => $totals['total_centavos'],
                'deposit_percent' => (int) $attributes['deposit_percent'],
                'deposit_centavos' => $totals['deposit_centavos'],
                'balance_centavos' => $totals['balance_centavos'],
                'validity_hours' => (int) $attributes['validity_hours'],
                'terms' => (string) ($attributes['terms'] ?? ''),
            ])->save();

            foreach ($incomingItems as $index => $row) {
                /** @var QuotationItem $item */
                $item = $existingItems->get((int) $row['id']);
                $item->fill([
                    'quantity' => $row['quantity'],
                    'specs' => ($row['specs'] ?? '') === '' ? null : $row['specs'],
                    'is_available' => (bool) $row['is_available'],
                    'unit_cost_centavos' => (int) $row['unit_cost_centavos'],
                    'line_cost_centavos' => $totals['items'][$index]['line_cost_centavos'],
                    'line_selling_centavos' => $totals['items'][$index]['line_selling_centavos'],
                ])->save();
            }

            return $locked->refresh()->load(['rfq.quotations', 'items']);
        });
    }

    public function discardDraft(Quotation $quotation): void
    {
        DB::transaction(function () use ($quotation): void {
            $locked = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);

            if ($locked->status !== 'Draft') {
                throw ValidationException::withMessages([
                    'status' => 'Only unsent draft quotations can be discarded.',
                ]);
            }

            $locked->delete();
        });
    }

    /** @return array{quotation: Quotation, email_sent: bool} */
    public function send(Quotation $quotation): array
    {
        $locked = DB::transaction(function () use ($quotation): Quotation {
            $rfq = Rfq::query()->lockForUpdate()->findOrFail($quotation->rfq_id);
            $this->expireOverdue($rfq->id);
            $locked = Quotation::query()->with('items')->lockForUpdate()->findOrFail($quotation->id);

            if ($locked->status !== 'Draft') {
                throw ValidationException::withMessages([
                    'status' => 'Only draft quotations can be sent.',
                ]);
            }

            if ($rfq->status === 'Closed') {
                throw ValidationException::withMessages([
                    'rfq' => 'Closed quote requests cannot be sent a quotation.',
                ]);
            }

            if ($rfq->quotations()->where('status', 'Accepted')->exists()) {
                throw ValidationException::withMessages([
                    'rfq' => 'This request already has an accepted quotation.',
                ]);
            }

            $available = $locked->items->where('is_available', true);

            if ($available->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Mark at least one item as available before sending the quotation.',
                ]);
            }

            if ($available->contains(fn (QuotationItem $item): bool => $item->unit_cost_centavos <= 0)) {
                throw ValidationException::withMessages([
                    'items' => 'Enter a unit cost greater than zero for every available item, or mark unavailable items accordingly.',
                ]);
            }

            if ($locked->total_centavos <= 0) {
                throw ValidationException::withMessages([
                    'total_centavos' => 'The quotation total must be greater than zero before sending.',
                ]);
            }

            if (trim($locked->terms) === '') {
                throw ValidationException::withMessages([
                    'terms' => 'Add customer-facing terms before sending the quotation.',
                ]);
            }

            $rfq->quotations()
                ->where('status', 'Sent')
                ->where('id', '!=', $locked->id)
                ->update(['status' => 'Superseded']);

            $plainToken = Str::random(64);
            $now = now();

            $locked->fill([
                'status' => 'Sent',
                'sent_at' => $now,
                'valid_until' => $now->copy()->addHours($locked->validity_hours),
                'access_token_hash' => hash('sha256', $plainToken),
                'access_token_encrypted' => $plainToken,
                'last_email_error' => null,
            ])->save();

            $rfq->status = 'Quoted';
            $rfq->save();

            return $locked->refresh()->load(['rfq.quotations', 'items']);
        });

        return $this->deliverEmail($locked);
    }

    /** @return array{quotation: Quotation, email_sent: bool} */
    public function resend(Quotation $quotation): array
    {
        $fresh = $this->findForAdmin($quotation);

        if ($fresh->status !== 'Sent' || $this->shareUrl($fresh) === null) {
            throw ValidationException::withMessages([
                'status' => 'Only active sent quotations can be re-emailed.',
            ]);
        }

        return $this->deliverEmail($fresh);
    }

    public function cancel(Quotation $quotation, ?string $reason = null): Quotation
    {
        return DB::transaction(function () use ($quotation, $reason): Quotation {
            $rfq = Rfq::query()->lockForUpdate()->findOrFail($quotation->rfq_id);
            $this->expireOverdue($rfq->id);
            $locked = Quotation::query()->lockForUpdate()->findOrFail($quotation->id);

            if ($locked->status !== 'Sent') {
                throw ValidationException::withMessages([
                    'status' => 'Only active sent quotations can be withdrawn.',
                ]);
            }

            $locked->fill([
                'status' => 'Cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => ($reason ?? '') === '' ? null : $reason,
            ])->save();

            $hasOtherLiveOrAccepted = $rfq->quotations()
                ->whereIn('status', ['Sent', 'Accepted'])
                ->exists();

            if ($rfq->status === 'Quoted' && ! $hasOtherLiveOrAccepted) {
                $rfq->status = 'Ready';
                $rfq->save();
            }

            return $locked->refresh()->load(['rfq.quotations', 'items']);
        });
    }

    /** @return array{quotation: Quotation, created: bool} */
    public function revise(Quotation $quotation, User $admin): array
    {
        return DB::transaction(function () use ($quotation, $admin): array {
            $rfq = Rfq::query()->lockForUpdate()->findOrFail($quotation->rfq_id);
            $this->expireOverdue($rfq->id);
            $source = Quotation::query()->with('items')->lockForUpdate()->findOrFail($quotation->id);

            if ($source->status === 'Draft') {
                throw ValidationException::withMessages([
                    'status' => 'This quotation is already a draft.',
                ]);
            }

            if ($rfq->status === 'Closed') {
                throw ValidationException::withMessages([
                    'rfq' => 'Closed quote requests cannot receive a revision.',
                ]);
            }

            if ($rfq->quotations()->where('status', 'Accepted')->exists()) {
                throw ValidationException::withMessages([
                    'rfq' => 'This request already has an accepted quotation.',
                ]);
            }

            $existingDraft = $rfq->quotations()->where('status', 'Draft')->first();

            if ($existingDraft !== null) {
                return [
                    'quotation' => $existingDraft->load(['rfq.quotations', 'items']),
                    'created' => false,
                ];
            }

            $nextVersion = ((int) $rfq->quotations()->max('version')) + 1;

            $revision = Quotation::query()->create([
                'rfq_id' => $rfq->id,
                'reference' => 'QT-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
                'version' => $nextVersion,
                'status' => 'Draft',
                'seafood_cost_centavos' => $source->seafood_cost_centavos,
                'handling_centavos' => $source->handling_centavos,
                'markup_percent' => $source->markup_percent,
                'markup_centavos' => $source->markup_centavos,
                'subtotal_centavos' => $source->subtotal_centavos,
                'delivery_centavos' => $source->delivery_centavos,
                'total_centavos' => $source->total_centavos,
                'deposit_percent' => $source->deposit_percent,
                'deposit_centavos' => $source->deposit_centavos,
                'balance_centavos' => $source->balance_centavos,
                'validity_hours' => $source->validity_hours,
                'terms' => $source->terms,
                'created_by' => $admin->id,
            ]);

            foreach ($source->items as $item) {
                $revision->items()->create([
                    'rfq_item_id' => $item->rfq_item_id,
                    'product_name' => $item->product_name,
                    'unit' => $item->unit,
                    'quantity' => $item->quantity,
                    'specs' => $item->specs,
                    'is_available' => $item->is_available,
                    'unit_cost_centavos' => $item->unit_cost_centavos,
                    'line_cost_centavos' => $item->line_cost_centavos,
                    'line_selling_centavos' => $item->line_selling_centavos,
                ]);
            }

            return [
                'quotation' => $revision->refresh()->load(['rfq.quotations', 'items']),
                'created' => true,
            ];
        });
    }

    public function findForGuest(string $token): Quotation
    {
        $quotation = Quotation::query()
            ->with(['rfq', 'items'])
            ->where('access_token_hash', hash('sha256', $token))
            ->where('status', '!=', 'Draft')
            ->first();

        if ($quotation === null) {
            throw (new ModelNotFoundException)->setModel(Quotation::class);
        }

        return $this->refreshExpiry($quotation)->load(['rfq', 'items']);
    }

    public function acceptByGuest(string $token): Quotation
    {
        return DB::transaction(function () use ($token): Quotation {
            $quotation = Quotation::query()
                ->where('access_token_hash', hash('sha256', $token))
                ->where('status', '!=', 'Draft')
                ->lockForUpdate()
                ->first();

            if ($quotation === null) {
                throw (new ModelNotFoundException)->setModel(Quotation::class);
            }

            $rfq = Rfq::query()->lockForUpdate()->findOrFail($quotation->rfq_id);
            $this->refreshExpiry($quotation);

            if ($quotation->status !== 'Sent') {
                throw ValidationException::withMessages([
                    'status' => $this->guestStatusError($quotation->status),
                ]);
            }

            if ($rfq->quotations()->where('status', 'Accepted')->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Another quotation for this request has already been accepted.',
                ]);
            }

            $quotation->fill([
                'status' => 'Accepted',
                'accepted_at' => now(),
            ])->save();

            return $quotation->refresh()->load(['rfq', 'items']);
        });
    }

    public function declineByGuest(string $token, ?string $reason = null): Quotation
    {
        return DB::transaction(function () use ($token, $reason): Quotation {
            $quotation = Quotation::query()
                ->where('access_token_hash', hash('sha256', $token))
                ->where('status', '!=', 'Draft')
                ->lockForUpdate()
                ->first();

            if ($quotation === null) {
                throw (new ModelNotFoundException)->setModel(Quotation::class);
            }

            $this->refreshExpiry($quotation);

            if ($quotation->status !== 'Sent') {
                throw ValidationException::withMessages([
                    'status' => $this->guestStatusError($quotation->status),
                ]);
            }

            $quotation->fill([
                'status' => 'Declined',
                'declined_at' => now(),
                'decline_reason' => ($reason ?? '') === '' ? null : $reason,
            ])->save();

            return $quotation->refresh()->load(['rfq', 'items']);
        });
    }

    private function guestStatusError(string $status): string
    {
        return match ($status) {
            'Accepted' => 'This quotation has already been accepted.',
            'Declined' => 'This quotation has already been declined.',
            'Expired' => 'This quotation has expired. Please request a fresh quote.',
            'Superseded' => 'A newer revision of this quotation has been sent to your email.',
            'Cancelled' => 'This quotation was withdrawn by IslaSource Seafoods.',
            default => 'This quotation is no longer open for a response.',
        };
    }

    /** @return array{quotation: Quotation, email_sent: bool} */
    private function deliverEmail(Quotation $quotation): array
    {
        $shareUrl = $this->shareUrl($quotation);

        if ($shareUrl === null) {
            return ['quotation' => $quotation, 'email_sent' => false];
        }

        try {
            Mail::to($quotation->rfq->customer_email)->send(new QuotationSentMail($quotation, $shareUrl));
            $quotation->forceFill([
                'last_emailed_at' => now(),
                'last_email_error' => null,
            ])->save();

            return [
                'quotation' => $quotation->refresh()->load(['rfq.quotations', 'items']),
                'email_sent' => true,
            ];
        } catch (Throwable $exception) {
            Log::error('Failed to send quotation email.', [
                'quotation_id' => $quotation->id,
                'reference' => $quotation->reference,
                'exception' => $exception->getMessage(),
            ]);

            $quotation->forceFill([
                'last_email_error' => 'Email delivery failed. You can copy the quote link or retry sending.',
            ])->save();

            return [
                'quotation' => $quotation->refresh()->load(['rfq.quotations', 'items']),
                'email_sent' => false,
            ];
        }
    }
}
