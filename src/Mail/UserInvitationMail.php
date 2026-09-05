<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Mail;

use BunnySidecar\BunnySidecar\BunnySidecar;
use BunnySidecar\BunnySidecar\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use SensitiveParameter;

class UserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invitation $invitation,
        #[SensitiveParameter] public string $plainToken,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('You have been invited to :app', ['app' => app(BunnySidecar::class)->name()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'bunny-sidecar::mail.user-invitation',
            with: [
                'acceptUrl' => route('bunny-sidecar.invitations.accept', $this->plainToken),
                'appName' => app(BunnySidecar::class)->name(),
            ],
        );
    }
}
