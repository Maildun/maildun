---
paths:
  - 'app/{Services/SesFeedbackVerifier.php,Http/Controllers/Teams/TeamEmailIntegrationController.php}'
---

# Services Controllers Teams

## An SES connection test must prove feedback, not just delivery
A successful SES send proves nothing about reporting: without a configuration set publishing DELIVERY/BOUNCE/COMPLAINT to the stored SNS topic, campaigns report zero and hard bounces never suppress subscribers.

The connection test therefore sends first (so credential errors stay specific), then runs SesFeedbackVerifier::verify() before the authorization transaction. A non-null reason blocks authorization, which blocks activation. Do not reorder these or make the check advisory.

The IAM user needs ses:SendEmail and ses:GetConfigurationSetEventDestinations. Never surface or chain the AwsException — map the error code to guidance, since workspace credentials are in scope. Tests fake it via fakeSesFeedbackVerification() in tests/Pest.php, or subclass the verifier and override client() with an AWS MockHandler (set 'retries' => 0, or retryable error codes drain the mock queue).
