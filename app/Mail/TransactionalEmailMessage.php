<?php

namespace App\Mail;

use App\Actions\Emails\ConvertHtmlToText;
use App\Enums\EmailProvider;
use App\Models\TransactionalEmailDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransactionalEmailMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TransactionalEmailDelivery $delivery) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->delivery->from_address, $this->delivery->from_name),
            replyTo: $this->delivery->reply_to === null ? [] : [new Address($this->delivery->reply_to)],
            subject: $this->delivery->subject,
            metadata: $this->sesMessageTags(),
        );
    }

    /**
     * Get the message content definition. The text part is derived from the
     * HTML, because spam filters score HTML-only mail and some recipients
     * read plain text.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->delivery->html,
            text: 'mail.campaign-text',
            with: ['plainText' => app(ConvertHtmlToText::class)->handle($this->delivery->html)],
        );
    }

    /** @return array<string, string> */
    private function sesMessageTags(): array
    {
        if (EmailProvider::tryFrom($this->delivery->provider)?->publishesSesFeedback() !== true) {
            return [];
        }

        return ['transactional_delivery_uuid' => $this->delivery->uuid];
    }
}
