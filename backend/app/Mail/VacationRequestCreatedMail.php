<?php

namespace App\Mail;

use App\Models\VacationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VacationRequestCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public VacationRequest $vacationRequest
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nueva Solicitud de Vacaciones - ' . $this->vacationRequest->user->full_name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.vacation-request-created',
            with: [
                'vacationRequest' => $this->vacationRequest,
                'employee' => $this->vacationRequest->user,
            ],
        );
    }
}
