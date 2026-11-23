<?php

namespace App\Mail;

use App\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuotationSentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Quotation $quotation,
        public readonly string $shareUrl,
    ) {}

    public function envelope(): Envelope
    {
        $suffix = $this->quotation->version > 1 ? " (Revised v{$this->quotation->version})" : '';

        return new Envelope(
            subject: "Your IslaSource Seafoods Quotation ({$this->quotation->reference}){$suffix}",
        );
    }

    public function content(): Content
    {
        $availableItems = $this->quotation->items->where('is_available', true)->values();
        $unavailableItems = $this->quotation->items->where('is_available', false)->values();
        $validUntilManila = $this->quotation->valid_until
            ? $this->quotation->valid_until->copy()->timezone('Asia/Manila')->format('M j, Y, g:i A').' PHT'
            : '';

        return new Content(
            view: 'mail.quotation-sent',
            text: 'mail.quotation-sent-text',
            with: [
                'quotation' => $this->quotation,
                'rfq' => $this->quotation->rfq,
                'shareUrl' => $this->shareUrl,
                'availableItems' => $availableItems,
                'unavailableItems' => $unavailableItems,
                'validUntilManila' => $validUntilManila,
                'formatPeso' => static fn (int $centavos): string => 'PHP '.number_format($centavos / 100, 2),
            ],
        );
    }
}
