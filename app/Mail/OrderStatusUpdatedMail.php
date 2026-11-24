<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly string $orderUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Order Update ({$this->order->reference}): {$this->order->status}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.order-status-updated',
            text: 'mail.order-status-updated-text',
            with: [
                'order' => $this->order,
                'orderUrl' => $this->orderUrl,
            ],
        );
    }
}
