<?php

namespace App\Mail;

use App\Enums\EmailProvider;
use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamEmailIntegrationTest extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Team $team,
        public EmailProvider $provider,
        public ?string $fromAddress = null,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                $this->fromAddress
                    ?? $this->team->fresh()?->resolvedEmailFromAddress()
                    ?? 'delivery@example.com',
                $this->team->name,
            ),
            subject: __('Email delivery test for :team', ['team' => $this->team->name]),
            metadata: $this->provider === EmailProvider::AmazonSes ? ['maildun_test' => 'true'] : [],
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.team-email-integration-test',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
