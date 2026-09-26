<?php

namespace App\Enums;

/**
 * Why a campaign delivery did not go out, grouped so the report can show the
 * top causes. The failure_reason text stays the detailed, sanitized message.
 */
enum EmailFailureCode: string
{
    case ProviderUnavailable = 'provider_unavailable';
    case SenderUnauthorized = 'sender_unauthorized';
    case ProviderRefused = 'provider_refused';
    case NoReport = 'no_report';
    case SesRejected = 'ses_rejected';
    case TransientBounce = 'transient_bounce';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::ProviderUnavailable => __('Email delivery was not connected or tested'),
            self::SenderUnauthorized => __('The From address was not verified'),
            self::ProviderRefused => __('The provider refused the message'),
            self::NoReport => __('The send never reported back'),
            self::SesRejected => __('Amazon SES rejected the message'),
            self::TransientBounce => __('Temporary bounce from the recipient server'),
            self::Unknown => __('Other errors'),
        };
    }
}
