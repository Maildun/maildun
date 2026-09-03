<?php

namespace App\Mail;

use App\Models\EmailDelivery;
use App\Models\EmailDeliveryAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class CampaignEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EmailDelivery $delivery,
        public string $trackedHtml,
        public ?EmailDeliveryAttempt $attempt = null,
        public ?string $renderedSubject = null,
        public ?string $plainText = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $email = $this->delivery->email;
        $replyTo = $email->resolvedReplyTo();

        return new Envelope(
            from: new Address($email->resolvedFromAddress(), $email->resolvedFromName()),
            replyTo: $replyTo === null ? [] : [new Address($replyTo)],
            subject: $this->renderedSubject ?? $email->subject,
            metadata: $this->sesMessageTags(),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->trackedHtml,
            text: 'mail.campaign-text',
            with: ['plainText' => $this->plainText ?? strip_tags($this->trackedHtml)],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return $this->delivery->email->attachments
            ->map(fn ($attachment): Attachment => Attachment::fromStorageDisk($attachment->disk, $attachment->path)
                ->as($attachment->original_name)
                ->withMime($attachment->mime_type))
            ->all();
    }

    public function headers(): Headers
    {
        // RFC 8058 one-click: mail providers POST the List-Unsubscribe URL with
        // no session, which the signed route accepts. Gmail and Yahoo require
        // both headers on bulk mail, so they go on every campaign regardless of
        // which transport carries it.
        $headers = [
            'List-Unsubscribe' => '<'.URL::signedRoute('public.unsubscribe.store', ['delivery' => $this->delivery]).'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ];

        return new Headers(text: $headers);
    }

    /**
     * The SES message tags that signed SNS feedback is correlated back through.
     *
     * These travel as Symfony metadata headers because that is the only form
     * both SES transports translate into API message tags. The X-SES-MESSAGE-TAGS
     * header is read by the SES SMTP endpoint alone, so sending it over the API
     * would leave every notification uncorrelated. The configuration set is no
     * longer a header at all: it rides on the transport's ConfigurationSetName.
     *
     * @return array<string, string>
     */
    private function sesMessageTags(): array
    {
        $provider = $this->attempt?->provider->value ?? $this->delivery->provider;

        if ($provider !== 'ses') {
            return [];
        }

        $tags = [
            'campaign_uuid' => $this->delivery->email->uuid,
            'delivery_uuid' => $this->delivery->uuid,
        ];

        if ($this->attempt instanceof EmailDeliveryAttempt) {
            $tags['attempt_uuid'] = $this->attempt->uuid;
        }

        return $tags;
    }
}
