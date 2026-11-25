<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\OrderMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderMessageNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly OrderMessage $orderMessage,
        public readonly string $replyUrl,
        public readonly bool $forAdmin,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->forAdmin
            ? "[IslaSource Admin] New Customer Message on Order {$this->order->reference}"
            : "New Message on Your IslaSource Order ({$this->order->reference})";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.order-message-notification',
            text: 'mail.order-message-notification-text',
            with: [
                'order' => $this->order,
                'orderMessage' => $this->orderMessage,
                'replyUrl' => $this->replyUrl,
                'forAdmin' => $this->forAdmin,
            ],
        );
    }
}
