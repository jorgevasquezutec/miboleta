<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetByAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public ?string $newPassword;
    public bool $mustChangePassword;
    public string $loginUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, ?string $newPassword = null, bool $mustChangePassword = false)
    {
        $this->user = $user;
        $this->newPassword = $newPassword;
        $this->mustChangePassword = $mustChangePassword;
        $this->loginUrl = config('app.frontend_url', config('app.url')) . '/login';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu contraseña ha sido restablecida - ' . config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset-admin',
            with: [
                'user' => $this->user,
                'newPassword' => $this->newPassword,
                'mustChangePassword' => $this->mustChangePassword,
                'loginUrl' => $this->loginUrl,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
