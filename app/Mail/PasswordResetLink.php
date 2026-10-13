<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $resetUrl)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'IDOL RANKING - Reset Your Password');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.password-reset-link');
    }
}
