<?php

namespace App\Enums;

enum EmailProvider: string
{
    case Smtp = 'smtp';
    case AmazonSes = 'ses';
    case Sendgrid = 'sendgrid';
    case Mailgun = 'mailgun';
    case Resend = 'resend';
    case Postmark = 'postmark';

    public function label(): string
    {
        return match ($this) {
            self::Smtp => 'SMTP',
            self::AmazonSes => 'Amazon SES',
            self::Sendgrid => 'SendGrid',
            self::Mailgun => 'Mailgun',
            self::Resend => 'Resend',
            self::Postmark => 'Postmark',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Smtp => 'Send through a custom SMTP server. Campaign opens and clicks remain tracked, but SMTP provides no campaign delivery, bounce, or complaint feedback.',
            self::AmazonSes => 'Send through Amazon SES with campaign delivery, bounce, and complaint feedback from Amazon SNS.',
            self::Sendgrid => 'Send through SendGrid using an API key.',
            self::Mailgun => 'Send through Mailgun using regional SMTP credentials.',
            self::Resend => 'Send through Resend using an API key.',
            self::Postmark => 'Send through Postmark using a server token.',
        };
    }

    /** @return list<self> */
    public static function supported(): array
    {
        return [
            self::Smtp,
            self::AmazonSes,
        ];
    }

    public function isSupported(): bool
    {
        return in_array($this, self::supported(), true);
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $provider) => [
            'value' => $provider->value,
            'label' => $provider->label(),
            'description' => $provider->description(),
        ], self::supported());
    }
}
