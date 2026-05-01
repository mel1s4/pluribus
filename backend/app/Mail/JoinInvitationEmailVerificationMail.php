<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class JoinInvitationEmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $completionUrl,
        public string $communityName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Complete your registration — '.$this->communityName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.join-invitation-email-verification',
        );
    }
}
