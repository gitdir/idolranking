<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IdolRegistrationRequestNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $requestType,
        public readonly string $englishName,
        public readonly string $applicantNickname,
        public readonly string $submittedAt,
        public readonly string $reviewUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'IDOL RANKING - New registration request');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.idol-registration-request-notification');
    }
}
