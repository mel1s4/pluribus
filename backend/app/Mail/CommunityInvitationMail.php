<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CommunityInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $joinUrl,
        public string $communityName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invitation to '.$this->communityName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.community-invitation',
        );
    }
}
