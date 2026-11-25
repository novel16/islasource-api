<?php

namespace App\Services;

use App\Mail\RfqReceivedAdminMail;
use App\Mail\RfqReceivedCustomerMail;
use App\Mail\RfqScheduleRespondedAdminMail;
use App\Mail\RfqSupplyNoticeMail;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Rfq;
use App\Models\RfqNote;
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

class RfqService
{
    public function scheduleShareUrl(Rfq $rfq): ?string
    {
        $token = $rfq->schedule_token_encrypted;

        if (! is_string($token) || $token === '') {
            return null;
        }

        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:3000'), '/');

        return "{$frontendUrl}/request-update/{$token}";
    }

    /** @param array<string, mixed> $attributes */
    public function submit(array $attributes): Rfq
    {
        $rfq = DB::transaction(function () use ($attributes): Rfq {
            $items = $attributes['items'];
            $productIds = collect($items)->pluck('product_id')->filter()->unique()->values();
            $products = Product::query()
                ->where('status', 'Published')
                ->whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $reference = 'RFQ-'.now()->format('Y').'-'.Str::upper(Str::random(8));

            $rfq = Rfq::query()->create([
                'reference' => $reference,
                'customer_name' => $attributes['customer_name'],
                'customer_email' => $attributes['email'],
                'customer_phone' => $attributes['phone'],
                'delivery_location' => $attributes['location'],
                'requested_delivery_date' => $attributes['requested_delivery_date'] ?? null,
                'customer_notes' => $attributes['notes'] ?? null,
                'status' => 'New',
            ]);

            foreach ($items as $index => $item) {
                $product = $item['product_id'] === null ? null : $products->get($item['product_id']);

                if ($item['product_id'] !== null && $product === null) {
                    throw ValidationException::withMessages([
                        "items.$index.product_id" => 'This product is no longer available for quote requests.',
                    ]);
                }

                $requestedMilliunits = (int) round((float) $item['quantity'] * 1000);
                $minimumMilliunits = (int) round((float) $product?->min_order_quantity * 1000);

                if ($product !== null && $requestedMilliunits < $minimumMilliunits) {
                    throw ValidationException::withMessages([
                        "items.$index.quantity" => "The minimum request for {$product->name} is {$product->min_order_quantity} {$product->unit}.",
                    ]);
                }

                $rfq->items()->create([
                    'product_id' => $product?->id,
                    'product_name' => $product?->name ?? $item['name'],
                    'product_slug' => $product?->slug,
                    'unit' => $product?->unit ?? $item['unit'],
                    'quantity' => $item['quantity'],
                    'customer_notes' => $item['notes'] ?? null,
                ]);
            }

            return $rfq->load('items');
        });

        $this->notifySubmitted($rfq);

        return $rfq;
    }

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, Rfq>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Rfq::query()->with('items');

        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }

        $search = $filters['search'] ?? null;

        if ($search !== null && $search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('reference', '%'.$search.'%')
                    ->orWhereLike('customer_name', '%'.$search.'%')
                    ->orWhereLike('customer_email', '%'.$search.'%')
                    ->orWhereHas('items', fn (Builder $items): Builder => $items->whereLike('product_name', '%'.$search.'%'));
            });
        }

        return $query->orderByDesc('id')->paginate($filters['per_page'] ?? 15)
            ->appends(array_filter($filters, fn (mixed $value): bool => $value !== null));
    }

    public function findOrFail(int $id): Rfq
    {
        Quotation::query()
            ->where('rfq_id', $id)
            ->where('status', 'Sent')
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', now())
            ->update(['status' => 'Expired']);

        return Rfq::query()->with(['items', 'notes.author', 'quotations'])->findOrFail($id);
    }

    public function updateStatus(Rfq $rfq, string $status): Rfq
    {
        return DB::transaction(function () use ($rfq, $status): Rfq {
            $locked = Rfq::query()->lockForUpdate()->findOrFail($rfq->id);

            if ($status === 'Closed') {
                Quotation::query()
                    ->where('rfq_id', $locked->id)
                    ->where('status', 'Sent')
                    ->whereNotNull('valid_until')
                    ->where('valid_until', '<', now())
                    ->update(['status' => 'Expired']);

                if ($locked->quotations()->where('status', 'Sent')->exists()) {
                    throw ValidationException::withMessages([
                        'status' => 'Cancel the active sent quotation before closing this request.',
                    ]);
                }
            }

            $locked->status = $status;
            $locked->save();

            return $locked->refresh()->load(['items', 'notes.author', 'quotations']);
        });
    }

    public function addNote(Rfq $rfq, User $author, string $body): RfqNote
    {
        return DB::transaction(function () use ($rfq, $author, $body): RfqNote {
            $locked = Rfq::query()->lockForUpdate()->findOrFail($rfq->id);
            $note = $locked->notes()->create([
                'user_id' => $author->id,
                'body' => $body,
            ]);

            return $note->load('author');
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{rfq: Rfq, email_sent: bool}
     */
    public function sendSupplyNotice(Rfq $rfq, array $attributes): array
    {
        $mode = (string) $attributes['mode'];

        $updated = DB::transaction(function () use ($rfq, $attributes, $mode): Rfq {
            $locked = Rfq::query()->lockForUpdate()->findOrFail($rfq->id);

            Quotation::query()
                ->where('rfq_id', $locked->id)
                ->where('status', 'Sent')
                ->whereNotNull('valid_until')
                ->where('valid_until', '<', now())
                ->update(['status' => 'Expired']);

            if ($locked->quotations()->where('status', 'Accepted')->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'This request already has an accepted quotation.',
                ]);
            }

            if ($locked->quotations()->where('status', 'Sent')->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Withdraw the active sent quotation before sending a supply notice.',
                ]);
            }

            $notice = trim((string) ($attributes['notice'] ?? ''));

            if ($mode === 'reschedule') {
                $plainToken = Str::random(64);
                $locked->fill([
                    'proposed_available_date' => $attributes['proposed_available_date'],
                    'proposed_delivery_date' => $attributes['proposed_delivery_date'],
                    'schedule_notice' => $notice === '' ? null : $notice,
                    'status' => 'Waiting',
                    'schedule_status' => 'Proposed',
                    'schedule_responded_at' => null,
                    'schedule_customer_note' => null,
                    'schedule_token_hash' => hash('sha256', $plainToken),
                    'schedule_token_encrypted' => $plainToken,
                ])->save();
            } else {
                if ($locked->status === 'Closed') {
                    throw ValidationException::withMessages([
                        'status' => 'This request is already closed.',
                    ]);
                }

                $locked->fill([
                    'schedule_notice' => $notice === '' ? null : $notice,
                    'status' => 'Closed',
                    'schedule_status' => 'ClosedUnavailable',
                    'schedule_token_hash' => null,
                    'schedule_token_encrypted' => null,
                ])->save();
            }

            return $locked->refresh()->load(['items', 'notes.author', 'quotations']);
        });

        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:3000'), '/');
        $actionUrl = $mode === 'reschedule'
            ? ($this->scheduleShareUrl($updated) ?? "{$frontendUrl}/seafood")
            : "{$frontendUrl}/seafood";

        $emailSent = true;

        try {
            Mail::to($updated->customer_email)->send(new RfqSupplyNoticeMail($updated, $mode, $actionUrl));
        } catch (Throwable $exception) {
            $emailSent = false;
            Log::error('Failed to send RFQ supply notice email to customer.', [
                'rfq_id' => $updated->id,
                'reference' => $updated->reference,
                'mode' => $mode,
                'exception' => $exception->getMessage(),
            ]);
        }

        return [
            'rfq' => $updated,
            'email_sent' => $emailSent,
        ];
    }

    public function findByScheduleToken(string $token): Rfq
    {
        $rfq = Rfq::query()
            ->with('items')
            ->where('schedule_token_hash', hash('sha256', $token))
            ->first();

        if ($rfq === null) {
            throw (new ModelNotFoundException)->setModel(Rfq::class);
        }

        return $rfq;
    }

    public function respondToScheduleByGuest(string $token, string $decision, ?string $note = null): Rfq
    {
        $updated = DB::transaction(function () use ($token, $decision, $note): Rfq {
            $locked = Rfq::query()
                ->with('items')
                ->where('schedule_token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw (new ModelNotFoundException)->setModel(Rfq::class);
            }

            if ($locked->quotations()->where('status', 'Accepted')->exists()) {
                throw ValidationException::withMessages([
                    'schedule_status' => 'A quotation has already been accepted for this request.',
                ]);
            }

            if ($locked->status === 'Closed' || $locked->schedule_status !== 'Proposed') {
                throw ValidationException::withMessages([
                    'schedule_status' => 'This schedule update has already been answered or is no longer open.',
                ]);
            }

            $cleanNote = trim((string) ($note ?? ''));

            if ($decision === 'accept') {
                $locked->fill([
                    'status' => 'Waiting',
                    'schedule_status' => 'Accepted',
                    'schedule_responded_at' => now(),
                    'schedule_customer_note' => $cleanNote === '' ? null : $cleanNote,
                ])->save();
            } else {
                $locked->fill([
                    'status' => 'Closed',
                    'schedule_status' => 'Declined',
                    'schedule_responded_at' => now(),
                    'schedule_customer_note' => $cleanNote === '' ? null : $cleanNote,
                ])->save();
            }

            return $locked->refresh()->load('items');
        });

        $this->notifyScheduleResponded($updated);

        return $updated;
    }

    private function notifyScheduleResponded(Rfq $rfq): void
    {
        $adminUrl = rtrim((string) config('app.admin_url', 'http://localhost:3001'), '/').'/rfqs/'.$rfq->id;

        foreach (User::activeAdminEmails() as $adminEmail) {
            try {
                Mail::to($adminEmail)->send(new RfqScheduleRespondedAdminMail($rfq, $adminUrl));
            } catch (Throwable $exception) {
                Log::error('Failed to send RFQ schedule response email to admin.', [
                    'rfq_id' => $rfq->id,
                    'reference' => $rfq->reference,
                    'admin_email' => $adminEmail,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function notifySubmitted(Rfq $rfq): void
    {
        try {
            Mail::to($rfq->customer_email)->send(new RfqReceivedCustomerMail($rfq));
        } catch (Throwable $exception) {
            Log::error('Failed to send RFQ confirmation email to customer.', [
                'rfq_id' => $rfq->id,
                'reference' => $rfq->reference,
                'exception' => $exception->getMessage(),
            ]);
        }

        $adminUrl = rtrim((string) config('app.admin_url', 'http://localhost:3001'), '/').'/rfqs/'.$rfq->id;

        foreach (User::activeAdminEmails() as $adminEmail) {
            try {
                Mail::to($adminEmail)->send(new RfqReceivedAdminMail($rfq, $adminUrl));
            } catch (Throwable $exception) {
                Log::error('Failed to send RFQ notification email to admin.', [
                    'rfq_id' => $rfq->id,
                    'reference' => $rfq->reference,
                    'admin_email' => $adminEmail,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }
}
