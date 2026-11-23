<?php

namespace App\Mail;

use App\Models\Rfq;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RfqReceivedAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Rfq $rfq,
        public readonly string $adminUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Quote Request ({$this->rfq->reference}) — {$this->rfq->customer_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.rfq-received-admin',
            text: 'mail.rfq-received-admin-text',
            with: [
                'rfq' => $this->rfq,
                'items' => $this->rfq->items,
                'adminUrl' => $this->adminUrl,
                'submittedAtManila' => $this->rfq->created_at
                    ? $this->rfq->created_at->copy()->timezone('Asia/Manila')->format('M j, Y, g:i A').' PHT'
                    : '',
            ],
        );
    }
}
