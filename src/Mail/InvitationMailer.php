<?php

declare(strict_types=1);

namespace BunnySidecar\BunnySidecar\Mail;

use BunnySidecar\BunnySidecar\Models\Invitation;
use Illuminate\Support\Facades\Mail;
use SensitiveParameter;

/**
 * Sends invitation mails, queued or synchronously depending on config.
 */
class InvitationMailer
{
    public function send(Invitation $invitation, #[SensitiveParameter] string $plainToken): void
    {
        $pending = Mail::to($invitation->email);
        $mailable = new UserInvitationMail($invitation, $plainToken);

        if (config()->boolean('bunny-sidecar.mail.queue', false)) {
            $pending->queue($mailable);

            return;
        }

        $pending->send($mailable);
    }
}
