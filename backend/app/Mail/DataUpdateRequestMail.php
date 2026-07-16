<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DataUpdateRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param User $requester Empleado que solicita la actualización de datos
     * @param Tenant $tenant Empresa activa del empleado
     * @param string $message Mensaje / detalle de lo que desea actualizar
     * @param string|array|null $requestedChanges Campos puntuales a actualizar (opcional)
     */
    public function __construct(
        public User $requester,
        public Tenant $tenant,
        public string $message,
        public string|array|null $requestedChanges = null,
    ) {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Solicitud de actualización de datos - ' . $this->requester->full_name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.data-update-request',
            with: [
                'requester' => $this->requester,
                'tenant' => $this->tenant,
                'message' => $this->message,
                'requestedChanges' => $this->requestedChanges,
            ],
        );
    }
}
