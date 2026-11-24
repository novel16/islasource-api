<?php

namespace App\Services;

use App\Mail\OrderPaidAdminMail;
use App\Mail\OrderPaidCustomerMail;
use App\Mail\OrderStatusUpdatedMail;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Quotation;
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

class OrderService
{
    public function __construct(private readonly PayMongoService $payMongo) {}

    public function trackingUrl(Order $order): ?string
    {
        $token = $order->access_token_encrypted;

        if (! is_string($token) || $token === '') {
            return null;
        }

        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:3000'), '/');

        return "{$frontendUrl}/order/{$token}";
    }

    public function createFromAcceptedQuotation(Quotation $quotation): Order
    {
        $existing = Order::query()->where('quotation_id', $quotation->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $quotation->loadMissing('rfq');
        $plainToken = Str::random(64);

        return Order::query()->create([
            'quotation_id' => $quotation->id,
            'rfq_id' => $quotation->rfq_id,
            'reference' => 'ORD-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
            'status' => 'Awaiting Payment',
            'payment_status' => 'Unpaid',
            'customer_name' => $quotation->rfq->customer_name,
            'customer_email' => $quotation->rfq->customer_email,
            'customer_phone' => $quotation->rfq->customer_phone,
            'delivery_location' => $quotation->rfq->delivery_location,
            'requested_delivery_date' => $quotation->rfq->requested_delivery_date,
            'subtotal_centavos' => $quotation->subtotal_centavos,
            'delivery_centavos' => $quotation->delivery_centavos,
            'total_centavos' => $quotation->total_centavos,
            'paid_centavos' => 0,
            'access_token_hash' => hash('sha256', $plainToken),
            'access_token_encrypted' => $plainToken,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Order::query()->with(['quotation.items', 'payments']);

        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }

        if ($filters['payment_status'] ?? null) {
            $query->where('payment_status', $filters['payment_status']);
        }

        $search = $filters['search'] ?? null;

        if ($search !== null && $search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('reference', '%'.$search.'%')
                    ->orWhereLike('customer_name', '%'.$search.'%')
                    ->orWhereLike('customer_email', '%'.$search.'%')
                    ->orWhereHas('quotation', fn (Builder $q): Builder => $q->whereLike('reference', '%'.$search.'%'));
            });
        }

        return $query->orderByDesc('id')->paginate($filters['per_page'] ?? 15)
            ->appends(array_filter($filters, fn (mixed $value): bool => $value !== null));
    }

    public function findForAdmin(Order $order): Order
    {
        return $order->load(['quotation.items', 'rfq', 'payments']);
    }

    public function findForGuest(string $token): Order
    {
        $order = Order::query()
            ->with(['quotation.items', 'payments'])
            ->where('access_token_hash', hash('sha256', $token))
            ->first();

        if ($order === null) {
            throw (new ModelNotFoundException)->setModel(Order::class);
        }

        return $order;
    }

    /** @return array{order: Order, checkout_url: string} */
    public function startCheckout(string $token): array
    {
        $order = $this->findForGuest($token);

        if ($order->payment_status === 'Paid') {
            throw ValidationException::withMessages([
                'status' => 'This order has already been paid.',
            ]);
        }

        if ($order->status !== 'Awaiting Payment') {
            throw ValidationException::withMessages([
                'status' => 'This order is no longer open for payment.',
            ]);
        }

        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:3000'), '/');
        $successUrl = "{$frontendUrl}/order/{$token}?payment=success";
        $cancelUrl = "{$frontendUrl}/order/{$token}?payment=cancelled";

        $session = $this->payMongo->createQrPhCheckoutSession($order, $successUrl, $cancelUrl);

        $order->payments()->create([
            'provider' => 'paymongo',
            'payment_method' => 'qrph',
            'status' => 'Pending',
            'amount_centavos' => $order->total_centavos,
            'paymongo_checkout_session_id' => $session['id'],
            'paymongo_payment_intent_id' => $session['payment_intent_id'],
            'checkout_url' => $session['checkout_url'],
        ]);

        return [
            'order' => $order->refresh()->load(['quotation.items', 'payments']),
            'checkout_url' => $session['checkout_url'],
        ];
    }

    public function verifyCheckoutPayment(string $token): Order
    {
        $order = $this->findForGuest($token);

        if ($order->payment_status === 'Paid' || $order->status === 'Cancelled') {
            return $order;
        }

        $latestPayment = $order->payments
            ->first(fn (OrderPayment $payment): bool => is_string($payment->paymongo_checkout_session_id) && $payment->paymongo_checkout_session_id !== '');

        if ($latestPayment === null) {
            return $order;
        }

        $sessionData = $this->payMongo->retrieveCheckoutSession((string) $latestPayment->paymongo_checkout_session_id);
        $paidPayment = $this->extractPaidPayment($sessionData, $order->total_centavos);

        if ($paidPayment !== null) {
            return $this->recordPaidPayment(
                orderId: $order->id,
                checkoutSessionId: (string) $latestPayment->paymongo_checkout_session_id,
                paymongoPaymentId: $paidPayment['id'],
                amountCentavos: $paidPayment['amount'],
            );
        }

        return $order->refresh()->load(['quotation.items', 'payments']);
    }

    /** @param array<string, mixed> $payload */
    public function handlePayMongoWebhook(array $payload): void
    {
        $eventType = data_get($payload, 'data.attributes.type');
        $eventData = data_get($payload, 'data.attributes.data');

        if (! is_string($eventType) || ! is_array($eventData)) {
            return;
        }

        if ($eventType === 'checkout_session.payment.paid') {
            $sessionId = data_get($eventData, 'id');
            $payment = is_string($sessionId) && $sessionId !== ''
                ? OrderPayment::query()->where('paymongo_checkout_session_id', $sessionId)->first()
                : null;

            $order = $payment?->order;

            if ($order === null) {
                $metadataOrderId = data_get($eventData, 'attributes.metadata.order_id');
                if (is_numeric($metadataOrderId)) {
                    $order = Order::query()->find((int) $metadataOrderId);
                }
            }

            if ($order === null) {
                return;
            }

            $paidPayment = $this->extractPaidPayment($eventData, $order->total_centavos);

            if ($paidPayment === null) {
                Log::warning('PayMongo checkout_session.payment.paid did not include a full paid payment.', [
                    'order_id' => $order->id,
                    'checkout_session_id' => $sessionId,
                ]);

                return;
            }

            $this->recordPaidPayment(
                orderId: $order->id,
                checkoutSessionId: is_string($sessionId) ? $sessionId : null,
                paymongoPaymentId: $paidPayment['id'],
                amountCentavos: $paidPayment['amount'],
            );

            return;
        }

        if (in_array($eventType, ['payment.failed', 'qrph.expired'], true)) {
            $intentId = data_get($eventData, 'attributes.payment_intent_id');

            if (is_string($intentId) && $intentId !== '') {
                OrderPayment::query()
                    ->where('paymongo_payment_intent_id', $intentId)
                    ->where('status', 'Pending')
                    ->update([
                        'status' => $eventType === 'qrph.expired' ? 'Expired' : 'Failed',
                        'failure_reason' => $eventType === 'qrph.expired'
                            ? 'QR Ph code expired before payment was completed.'
                            : (string) (data_get($eventData, 'attributes.last_payment_error.failed_message') ?? 'Payment failed.'),
                    ]);
            }
        }
    }

    public function updateStatus(Order $order, string $nextStatus, ?string $cancelReason = null): Order
    {
        $transitioned = DB::transaction(function () use ($order, $nextStatus, $cancelReason): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($nextStatus === 'Cancelled') {
                if ($locked->payment_status === 'Paid') {
                    throw ValidationException::withMessages([
                        'status' => 'Paid orders cannot be cancelled directly.',
                    ]);
                }

                if ($locked->status === 'Cancelled') {
                    throw ValidationException::withMessages([
                        'status' => 'This order is already cancelled.',
                    ]);
                }

                $locked->fill([
                    'status' => 'Cancelled',
                    'cancelled_at' => now(),
                    'cancel_reason' => ($cancelReason ?? '') === '' ? null : trim($cancelReason),
                ])->save();

                return $locked->refresh()->load(['quotation.items', 'rfq', 'payments']);
            }

            $allowedNext = [
                'Processing' => 'Packed',
                'Packed' => 'Dispatched',
                'Dispatched' => 'Completed',
            ];

            if ($locked->payment_status !== 'Paid') {
                throw ValidationException::withMessages([
                    'status' => 'Full QR Ph payment must be verified before processing this order.',
                ]);
            }

            if (($allowedNext[$locked->status] ?? null) !== $nextStatus) {
                throw ValidationException::withMessages([
                    'status' => "Cannot move order from {$locked->status} to {$nextStatus}.",
                ]);
            }

            $timestampField = match ($nextStatus) {
                'Packed' => 'packed_at',
                'Dispatched' => 'dispatched_at',
                'Completed' => 'completed_at',
            };

            $locked->fill([
                'status' => $nextStatus,
                $timestampField => now(),
            ])->save();

            return $locked->refresh()->load(['quotation.items', 'rfq', 'payments']);
        });

        if (in_array($transitioned->status, ['Packed', 'Dispatched', 'Completed'], true)) {
            $this->notifyStatusUpdated($transitioned);
        }

        return $transitioned;
    }

    private function recordPaidPayment(
        int $orderId,
        ?string $checkoutSessionId,
        ?string $paymongoPaymentId,
        int $amountCentavos,
    ): Order {
        $result = DB::transaction(function () use ($orderId, $checkoutSessionId, $paymongoPaymentId, $amountCentavos): array {
            $locked = Order::query()->lockForUpdate()->findOrFail($orderId);

            if ($locked->payment_status === 'Paid') {
                return [
                    'order' => $locked->load(['quotation.items', 'payments']),
                    'newly_paid' => false,
                ];
            }

            $now = now();
            $payment = $checkoutSessionId !== null
                ? $locked->payments()->where('paymongo_checkout_session_id', $checkoutSessionId)->first()
                : null;

            if ($payment !== null) {
                $payment->fill([
                    'status' => 'Paid',
                    'paymongo_payment_id' => $paymongoPaymentId ?? $payment->paymongo_payment_id,
                    'amount_centavos' => $amountCentavos,
                    'paid_at' => $now,
                    'failure_reason' => null,
                ])->save();
            } else {
                $locked->payments()->create([
                    'provider' => 'paymongo',
                    'payment_method' => 'qrph',
                    'status' => 'Paid',
                    'amount_centavos' => $amountCentavos,
                    'paymongo_checkout_session_id' => $checkoutSessionId,
                    'paymongo_payment_id' => $paymongoPaymentId,
                    'paid_at' => $now,
                ]);
            }

            $locked->fill([
                'payment_status' => 'Paid',
                'paid_centavos' => $amountCentavos,
                'paid_at' => $now,
                'status' => $locked->status === 'Awaiting Payment' ? 'Processing' : $locked->status,
            ])->save();

            return [
                'order' => $locked->refresh()->load(['quotation.items', 'payments']),
                'newly_paid' => true,
            ];
        });

        if ($result['newly_paid']) {
            $this->notifyPaid($result['order']);
        }

        return $result['order'];
    }

    /**
     * @param  array<string, mixed>  $sessionData
     * @return array{id: ?string, amount: int}|null
     */
    private function extractPaidPayment(array $sessionData, int $expectedCentavos): ?array
    {
        $payments = data_get($sessionData, 'attributes.payments', []);

        if (is_array($payments)) {
            foreach ($payments as $payment) {
                if (! is_array($payment)) {
                    continue;
                }

                $status = data_get($payment, 'attributes.status');
                $amount = (int) data_get($payment, 'attributes.amount', 0);

                if ($status === 'paid' && $amount >= $expectedCentavos) {
                    $id = data_get($payment, 'id');

                    return [
                        'id' => is_string($id) && $id !== '' ? $id : null,
                        'amount' => $amount,
                    ];
                }
            }
        }

        return null;
    }

    private function notifyPaid(Order $order): void
    {
        $orderUrl = $this->trackingUrl($order) ?? rtrim((string) config('app.frontend_url', 'http://localhost:3000'), '/');

        try {
            Mail::to($order->customer_email)->send(new OrderPaidCustomerMail($order, $orderUrl));
        } catch (Throwable $exception) {
            Log::error('Failed to send order payment receipt email to customer.', [
                'order_id' => $order->id,
                'reference' => $order->reference,
                'exception' => $exception->getMessage(),
            ]);
        }

        $adminUrl = rtrim((string) config('app.admin_url', 'http://localhost:3001'), '/').'/orders/'.$order->id;
        $adminEmails = User::query()
            ->where('is_admin', true)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id')
            ->pluck('email')
            ->unique()
            ->values();

        foreach ($adminEmails as $adminEmail) {
            try {
                Mail::to($adminEmail)->send(new OrderPaidAdminMail($order, $adminUrl));
            } catch (Throwable $exception) {
                Log::error('Failed to send order paid notification to admin.', [
                    'order_id' => $order->id,
                    'reference' => $order->reference,
                    'admin_email' => $adminEmail,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function notifyStatusUpdated(Order $order): void
    {
        $orderUrl = $this->trackingUrl($order) ?? rtrim((string) config('app.frontend_url', 'http://localhost:3000'), '/');

        try {
            Mail::to($order->customer_email)->send(new OrderStatusUpdatedMail($order, $orderUrl));
        } catch (Throwable $exception) {
            Log::error('Failed to send order status email to customer.', [
                'order_id' => $order->id,
                'reference' => $order->reference,
                'status' => $order->status,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
