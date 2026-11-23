<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Lead $lead,
        /** Serviço e mensagem deste envio: num novo contato, diferem dos gravados na solicitação. */
        public ?string $product = null,
        public ?string $contactMessage = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Novo contato pelo site — '.($this->lead->company_name ?? $this->lead->contact_name),
            replyTo: $this->lead->email ? [$this->lead->email] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contact-form-received',
            with: [
                'lead' => $this->lead,
                'serviceLabel' => Lead::PRODUCTS[$this->product ?? $this->lead->product] ?? $this->lead->product,
                'contactMessage' => $this->contactMessage ?? $this->lead->notes,
                'adminUrl' => route('solicitacoes.show', $this->lead),
            ],
        );
    }
}
