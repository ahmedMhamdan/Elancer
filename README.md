# Elancer

## Profile photo moderation

Ahmed approved sending cropped profile photos to Sightengine on 2026-09-16. Identity documents are excluded. Existing private photos remain private; this does not publish profiles.

### Local setup

Create a Sightengine account and an image moderation workflow using its [official guide](https://sightengine.com/docs/image-moderation-workflows). Configure rejection rules for the content you want to exclude, including nudity/sexual content and graphic violence. Review the workflow rules and test representative acceptable and unacceptable images before enabling uploads for members. Detection is probabilistic, not a guarantee.

Set SIGHTENGINE_API_USER, SIGHTENGINE_API_SECRET and SIGHTENGINE_PROFILE_WORKFLOW in the local environment, using the API credentials and workflow ID from the provider dashboard. Never commit values. Run php artisan config:clear after changing local configuration; deployments using cached configuration must rebuild that cache. No account, subscription or paid plan was provisioned by this change.

### Request and save flow

The existing browser editor crops the selected image. Laravel checks authorization, file type, size and dimensions, re-encodes it as a JPEG with a maximum 512-pixel edge, and removes original metadata. PrepareProfilePhoto sends those normalized bytes as multipart media to the fixed HTTPS check-workflow.json endpoint with server-side credentials and the configured workflow ID. It does not send a public URL, user identifier or identity document.

Only a successful response with the matching workflow ID and summary.action equal to accept permits saving. Rejection, missing configuration, malformed responses, timeouts and service errors leave the existing photo unchanged. Connections time out after three seconds; requests after ten. Redirects are disabled. Provider response bodies and credentials are not logged by this action.

Uploads are limited per account to one attempt per minute and three per hour across onboarding and profile editing. Onboarding without a photo remains available. A database transaction locks the account, saves the replacement privately, and removes the old file after commit; a failed save cleans up the new file. The avatar URL changes to refresh the displayed image.

### Verification and limits

Feature tests mock the provider; no real photos are sent during tests. They cover private ownership, normalization, rate limits, rejection, unknown decisions, connection failure, missing configuration, malformed responses, mismatched workflows, HTTP errors and database rollback. Live provider configuration and a representative moderation acceptance review remain necessary. Existing photos are not retroactively screened.
