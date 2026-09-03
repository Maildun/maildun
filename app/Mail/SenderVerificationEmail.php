<?php

namespace App\Mail;

use App\Models\TeamSender;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SenderVerificationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TeamSender $sender, public string $verificationUrl) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->sender->email, $this->sender->name),
            replyTo: $this->sender->reply_to === null ? [] : [new Address($this->sender->reply_to)],
            subject: __('Verify :address as a sender', ['address' => $this->sender->email]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.sender-verification',
            with: [
                'sender' => $this->sender,
                'verificationUrl' => $this->verificationUrl,
            ],
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
