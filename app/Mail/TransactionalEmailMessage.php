<?php

namespace App\Mail;

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
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            htmlString: $this->delivery->html,
        );
    }
}
