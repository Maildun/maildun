<?php

namespace App\Mail;

use App\Models\TransactionalEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A single test copy of a transactional email, with merge tags already rendered.
 */
class TransactionalEmailTest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TransactionalEmail $email,
        public string $subjectLine,
        public string $htmlBody,
    ) {}

    public function envelope(): Envelope
    {
        $replyTo = $this->email->resolvedReplyTo();

        return new Envelope(
            from: new Address($this->email->resolvedFromAddress(), $this->email->resolvedFromName()),
            replyTo: $replyTo === null ? [] : [new Address($replyTo)],
            subject: '[Test] '.$this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlBody,
        );
    }
}
