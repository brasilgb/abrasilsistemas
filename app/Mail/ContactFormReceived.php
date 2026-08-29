<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Lead $lead,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Novo contato pelo site — '.$this->lead->company_name,
            replyTo: $this->lead->email ? [$this->lead->email] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contact-form-received',
            with: [
                'lead' => $this->lead,
                'serviceLabel' => Lead::PRODUCTS[$this->lead->product] ?? $this->lead->product,
                'adminUrl' => route('leads.index'),
            ],
        );
    }
}
