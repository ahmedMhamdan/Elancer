# Elancer

Elancer is a freelance marketplace in English and Arabic. Clients post fixed-price projects and hire one freelancer per project. Freelancers publish a profile, apply with proposals and deliver the agreed work.

It is a portfolio project in active development. Payments run in provider test mode only: no real money moves, and nothing is paid out.

## What works today

### Accounts

- Registration by email with verification and password recovery, or sign-in with Google or GitHub.
- Two-factor authentication and passkeys.
- Four-step onboarding that sets the workspace (client or freelancer), profile, photo and location.
- Profile photos can be cropped, zoomed and rotated, and pass an automated safety check before they are stored.

### Projects and discovery

- Guided project drafts with autosave, recovery when two tabs save different versions, and a Trash with restore.
- Publishing with a category, skills, a target budget or a range, an application cutoff and up to three screening questions.
- Public project search by keyword, category, skills, budget and posting date, ordered by skill match or newest.
- A public project page that shows how many proposals were received, never who applied or what they offered.
- A talent directory with skill and availability filters, and public freelancer profiles with reviews and case studies.

### Hiring

- Private proposal drafts with autosave, submission, revision history, withdrawal and resubmission.
- Clients shortlist, archive, keep private notes, decline, reopen and compare up to three proposals.
- Invitations to an open project, with a seven-day wait before inviting again after a decline.
- Private conversations that only the client can start, and only after a proposal exists.
- Senders can correct a message for 15 minutes; the correction is marked and its history kept.
- Final offers with scope, named deliverables, price, duration and revision rounds. An offer expires after 72 hours, and a project has one pending offer at a time. Accepting one creates a contract.

### Contracts

- Funding through Stripe or Moyasar in test mode. The server confirms every payment with the provider; a browser return alone never activates a contract.
- A workroom with deliveries (files and links), grouped revision requests, agreed changes to dates and revision rounds, and an activity record.
- Cancellation by mutual agreement. A funded contract ends only after the provider confirms the refund.
- Completion, then one review from each side (1 to 5 stars and text), published when both are in or after 14 days.
- Portfolio case studies with images. A case study about work done on Elancer is published only after the client approves the exact content, and the client can withdraw that approval later.

### Safety and administration

- Members can report projects, profiles, messages, contracts and case studies, follow the progress of their reports, and block other members.
- Administrators work through a report queue, read a reported conversation with that access recorded, and hide or restore a reported message, project or case study.
- Administrators suspend and reinstate ordinary members with a reason. A suspended member keeps restricted access to existing contracts.
- Category management, administrator access managed by a super administrator, and manual identity review.
- Administration pages require two-factor authentication in the current session. Moderation, suspension and access changes need a reason and leave an audit record.

### Across the application

- English and Arabic for every interface string, with right-to-left layout for Arabic.
- Light and dark themes, usable from phone width to desktop.
- In-app notifications with live updates, and email preferences by category.

## Not built yet

- Saved projects, saved searches and saved talent.
- Switching between the client and freelancer workspace after onboarding.
- Real projects and talent on the home page (it shows illustrative samples).
- Editing a published project: extending the cutoff and adding dated clarifications.
- File attachments on briefs, proposals and messages.
- An "any skill" search mode and a filter by number of proposals.
- An API with access tokens.
- A searchable help centre.
- A hosted public demo. The Supabase database for it is being set up; the application host is not chosen yet.

## Stack

| Layer    | Tools                                                                                    |
| -------- | ---------------------------------------------------------------------------------------- |
| Backend  | PHP 8.3, Laravel 13, Fortify, Socialite, Reverb                                          |
| Frontend | React 19, TypeScript, Inertia 3, Tailwind CSS 4, Vite                                    |
| Database | MySQL in local development, PostgreSQL on Supabase for the hosted site, SQLite for tests |
| Quality  | PHPUnit, PHPStan (Larastan), Pint, and a translation coverage check                      |

### Why Supabase

Laravel does not need Supabase. It runs on any MySQL or PostgreSQL server, and this application uses Supabase only as a managed PostgreSQL database, reached through Laravel's ordinary database connection. Supabase Auth, Storage and the Data API are not used: sign-in, permissions and files all stay in Laravel.

Supabase was chosen so the author could learn it, and PostgreSQL with it, on a real project: private schemas, separate roles for migrations and for the running application, verified TLS connections, and moving existing data from MySQL without losing it.

## Getting started

You need PHP 8.3, Composer 2 and Node.js 22.

```bash
git clone https://github.com/ahmedMhamdan/Elancer.git
cd Elancer
composer setup
composer dev
```

`composer setup` installs the dependencies, creates `.env` from `.env.example`, generates the application key, runs the migrations and builds the frontend. The example environment uses SQLite, so no database server is needed for a first run. `composer dev` starts the local development processes; the application is then at `http://localhost:8000`.

### First administrator

Register an account, verify its email, then run:

```bash
php artisan app:make-super-admin you@example.com
```

Turn on two-factor authentication for that account before opening the administration pages.

## Configuration

Everything below is optional. Without it the related feature stays off, and the rest of the application works. Variable names are in `.env.example`; never commit real values.

| Feature               | Variables                                                                        | Notes                                                                                                  |
| --------------------- | -------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| Email                 | `MAIL_*`                                                                         | Written to the log by default. Emails are queued, so a queue worker must be running.                   |
| Live updates          | `BROADCAST_CONNECTION`, `REVERB_APP_*`                                           | Set the connection to `reverb` and run `php artisan reverb:start`.                                     |
| Google and GitHub     | `GOOGLE_*`, `GITHUB_*`                                                           | Register the exact callbacks `/auth/google/callback` and `/auth/github/callback` with each provider.   |
| Stripe test payments  | `STRIPE_TEST_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`                                | Only `sk_test_` keys are accepted. The webhook at `/payments/webhooks/stripe` is off without a secret. |
| Moyasar test payments | `MOYASAR_TEST_SECRET_KEY`                                                        | Only `sk_test_` keys are accepted.                                                                     |
| Photo safety check    | `SIGHTENGINE_API_USER`, `SIGHTENGINE_API_SECRET`, `SIGHTENGINE_PROFILE_WORKFLOW` | Without these, photo and portfolio image uploads are refused. Onboarding without a photo still works.  |

Pending payments and refunds are rechecked with the providers every five minutes by `payments:reconcile`. Run `php artisan schedule:work` locally to have that happen.

Run `php artisan config:clear` after changing the environment file.

## Photo safety check

Profile photos and portfolio images are checked by [Sightengine](https://sightengine.com/docs/image-moderation-workflows) before they are stored. Identity documents are never sent to it.

- The browser crops the image. The server then checks its type, size and dimensions, re-encodes it as a JPEG and removes the original metadata.
- Only those normalized bytes are sent, over HTTPS with server-side credentials. No public address, account identifier or identity document goes with them.
- An image is saved only when the service answers with the configured workflow and the decision `accept`. A rejection, missing configuration, a malformed answer, a timeout or a service error all leave the current photo unchanged.
- Uploads are limited per account, and stored photos stay private: they are served through the application, not from a public folder.

The rules for what is rejected live in the Sightengine workflow, not in this repository. Detection is probabilistic, so review the workflow with representative images before relying on it. Photos stored before the check was added are not rechecked.

## Checks

```bash
npm run build
composer ci:check
```

Build first, because the tests render pages from the built assets. `composer ci:check` runs frontend formatting and lint, the Arabic translation coverage check, TypeScript, Pint, PHPStan and the PHP test suite. The test suite uses an in-memory SQLite database and never touches a local or hosted database. On GitHub the same PHP tests also run on PostgreSQL.

## Credits

Dashboard components are adapted from TailAdmin. Sources and licences are listed in [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
