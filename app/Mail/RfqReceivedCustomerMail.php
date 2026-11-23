<?php

namespace App\Mail;

use App\Models\Rfq;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RfqReceivedCustomerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Rfq $rfq,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "We Received Your IslaSource Seafoods Quote Request ({$this->rfq->reference})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.rfq-received-customer',
            text: 'mail.rfq-received-customer-text',
            with: [
                'rfq' => $this->rfq,
                'items' => $this->rfq->items,
                'submittedAtManila' => $this->rfq->created_at
                    ? $this->rfq->created_at->copy()->timezone('Asia/Manila')->format('M j, Y, g:i A').' PHT'
                    : '',
            ],
        );
    }
}
