<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteEmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $customerName,
        public readonly string $code,
        public readonly int $validMinutes = 10,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->code} is your IslaSource Seafoods verification code",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.quote-email-verification',
            text: 'mail.quote-email-verification-text',
            with: [
                'customerName' => $this->customerName,
                'code' => $this->code,
                'validMinutes' => $this->validMinutes,
            ],
        );
    }
}
