<?php

namespace App\Mail;

use App\Models\Email;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A single test copy of a draft, sent to one address the author nominates so
 * they can see the email in a real inbox before it goes anywhere else.
 */
class ComposedEmailTest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Email $email,
        public string $renderedSubject,
        public string $renderedHtml,
        public string $plainText,
    ) {}

    public function envelope(): Envelope
    {
        $replyTo = $this->email->resolvedReplyTo();

        return new Envelope(
            from: new Address($this->email->resolvedFromAddress(), $this->email->resolvedFromName()),
            replyTo: $replyTo === null ? [] : [new Address($replyTo)],
            subject: '[Test] '.$this->renderedSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->renderedHtml,
            text: 'mail.campaign-text',
            with: ['plainText' => $this->plainText],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return $this->email->attachments
            ->map(fn ($attachment): Attachment => Attachment::fromStorageDisk($attachment->disk, $attachment->path)
                ->as($attachment->original_name)
                ->withMime($attachment->mime_type))
            ->all();
    }
}
