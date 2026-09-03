---
paths:
  - '{config/services.php,config/filesystems.php}'
---

# Config

## SES credentials are MAIL_SES_*, never the AWS_* S3 vars
services.ses reads MAIL_SES_KEY / MAIL_SES_SECRET / MAIL_SES_REGION / MAIL_SES_CONFIGURATION_SET / MAIL_SES_SNS_TOPIC_ARN. It must never fall back to AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY / AWS_DEFAULT_REGION: those belong to S3 object storage, and on an instance pointed at MinIO or R2 they are not SES credentials at all — sending would fail or reach the wrong AWS account.

Workspaces configure their own provider in the app (encrypted per connection; SES uses IAM keys on the ses-v2 API transport, not its SMTP endpoint). This config block only backs the optional platform-wide mailer for MAIL_MAILER=ses, and the same values gate the SNS webhook's platform-topic path. Renaming these is a breaking env change for anyone who had AWS_SES_* set.
