<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderPaidCustomerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly string $orderUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Payment Confirmed for Your IslaSource Order ({$this->order->reference})",
        );
    }

    public function content(): Content
    {
        $items = $this->order->quotation->items->where('is_available', true)->values();

        return new Content(
            view: 'mail.order-paid-customer',
            text: 'mail.order-paid-customer-text',
            with: [
                'order' => $this->order,
                'items' => $items,
                'orderUrl' => $this->orderUrl,
                'paidAtManila' => $this->order->paid_at
                    ? $this->order->paid_at->copy()->timezone('Asia/Manila')->format('M j, Y, g:i A').' PHT'
                    : '',
                'formatPeso' => static fn (int $centavos): string => 'PHP '.number_format($centavos / 100, 2),
            ],
        );
    }
}
