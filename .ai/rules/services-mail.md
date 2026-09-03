---
paths:
  - 'app/{Services,Mail}/**,config/mail.php'
---

# Services Mail

## SES sends over the API; X-SES-* headers do not work there
Workspace SES connections use IAM keys (access_key_id/secret_access_key) on the ses-v2 API transport, not SES SMTP credentials. Supersedes any rule saying "SES via its SMTP endpoint".

X-SES-CONFIGURATION-SET and X-SES-MESSAGE-TAGS are parsed by the SES SMTP endpoint only; SesTransport and SesV2Transport both ignore them. On the API path the configuration set must travel as options.ConfigurationSetName and the correlation tags as Symfony MetadataHeader (Envelope metadata), which the transports map to EmailTags. Get this wrong and sends still succeed while SES publishes nothing to SNS and ProcessSesEvent loses attempt_uuid — a silent, total loss of campaign feedback.

TeamMailer::sesConfiguration() must set key, secret, region and token explicitly: createSesV2Transport merges services.ses underneath, so an omitted key silently falls back to the platform IAM credentials and sends from the wrong AWS account.
