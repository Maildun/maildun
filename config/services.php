<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    /*
     * Email delivery is configured per workspace in the app, under Settings →
     * Email delivery, and those IAM credentials are encrypted per connection.
     * This block only backs the optional platform-wide SES mailer
     * (MAIL_MAILER=ses); MAIL_SES_CONFIGURATION_SET also reaches that mailer as
     * options.ConfigurationSetName in config/mail.php, which is what makes SES
     * publish feedback to SNS at all.
     *
     * It deliberately does not read AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY /
     * AWS_DEFAULT_REGION: those belong to S3 object storage, and on an instance
     * pointed at MinIO or R2 they are not SES credentials at all. Sending would
     * either fail or reach the wrong account. Set MAIL_SES_* explicitly instead.
     */
    'ses' => [
        'key' => env('MAIL_SES_KEY'),
        'secret' => env('MAIL_SES_SECRET'),
        'region' => env('MAIL_SES_REGION', 'us-east-1'),
        'configuration_set' => env('MAIL_SES_CONFIGURATION_SET'),
        'sns_topic_arn' => env('MAIL_SES_SNS_TOPIC_ARN'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
