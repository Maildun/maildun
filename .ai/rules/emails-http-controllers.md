---
paths:
  - 'resources/js/pages/emails/**,app/Http/Controllers/EmailController.php'
---

# Emails Http Controllers

## Campaign table leads with status and recipient avatars
The campaign index starts with a Sent or Draft badge derived from emails.sent_at. Recipients render up to three subscribed Micah avatars from the selected segment, falling back to the audience, followed by +N for the remaining subscribed recipients.

## Preview personalization on a dedicated fullscreen page
This supersedes the earlier Send & preview dialog. Draft setup exposes one Preview and Send action that opens emails/preview-and-send in the fullscreen AppLayout. The page uses PreviewWidthTabs, zoom, scoped recipient search plus previous/next navigation, server-rendered standard/custom merge data, and owns the final campaign send action.
