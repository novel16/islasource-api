<?php

namespace App\Mail;

use App\Models\Rfq;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RfqScheduleRespondedAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Rfq $rfq,
        public readonly string $adminUrl,
    ) {}

    public function envelope(): Envelope
    {
        $action = $this->rfq->schedule_status === 'Accepted'
            ? 'Agreed to Wait'
            : 'Cancelled Request';

        return new Envelope(
            subject: "[IslaSource Admin] {$this->rfq->customer_name} {$action} ({$this->rfq->reference})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.rfq-schedule-responded-admin',
            text: 'mail.rfq-schedule-responded-admin-text',
            with: [
                'rfq' => $this->rfq,
                'adminUrl' => $this->adminUrl,
            ],
        );
    }
}
