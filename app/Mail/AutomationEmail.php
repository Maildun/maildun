<?php

namespace App\Mail;

use App\Models\Subscriber;
use App\Models\TransactionalEmail;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class AutomationEmail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public TransactionalEmail $email,
        public Subscriber $subscriber,
        public string $subjectLine,
        public string $htmlBody,
    ) {}

    public function envelope(): Envelope
    {
        $replyTo = $this->email->resolvedReplyTo();

        return new Envelope(
            from: new Address($this->email->resolvedFromAddress(), $this->email->resolvedFromName()),
            replyTo: $replyTo === null ? [] : [new Address($replyTo)],
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlBody,
        );
    }

    public function headers(): Headers
    {
        // Automation mail is marketing mail, so it needs the same RFC 8058
        // treatment as a campaign. It has no EmailDelivery row to key the
        // opt-out to, so the signed route names the subscriber instead.
        return new Headers(text: [
            'List-Unsubscribe' => '<'.self::oneClickUrl($this->subscriber).'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    /**
     * The POST target providers call for one-click. Not for a body link: a
     * browser following it with GET would get a 405.
     */
    public static function oneClickUrl(Subscriber $subscriber): string
    {
        return URL::signedRoute(
            'public.unsubscribe.subscriber.store',
            ['subscriber' => $subscriber],
        );
    }

    /**
     * The confirmation page a human lands on, offered to authors as
     * {{ unsubscribe_url }} so the body can carry a visible opt-out.
     */
    public static function unsubscribeUrl(Subscriber $subscriber): string
    {
        return URL::signedRoute(
            'public.unsubscribe.subscriber.show',
            ['subscriber' => $subscriber],
        );
    }
}
