<?php

namespace App\Mail;

use App\Models\Share;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ShareReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Share $share,
        public readonly string $shareTitle,
        public readonly string $shareUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'A file or folder was shared with you in PMS Drive',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.share-received',
        );
    }
}
