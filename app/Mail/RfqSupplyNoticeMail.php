<?php

namespace App\Mail;

use App\Models\Rfq;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RfqSupplyNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Rfq $rfq,
        public readonly string $mode,
        public readonly string $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->mode === 'reschedule'
            ? "Supply & Delivery Schedule Update for Request {$this->rfq->reference}"
            : "Availability Update for Your Request ({$this->rfq->reference})";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.rfq-supply-notice',
            text: 'mail.rfq-supply-notice-text',
            with: [
                'rfq' => $this->rfq,
                'mode' => $this->mode,
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}
