# phpBB Mentions

A community-maintained phpBB extension for user and group mentions. Maintained by
**Fernando Coz** ([ferdcoz](https://github.com/ferdcoz)).

## Credits and license

This fork builds on [MoeMorox/mention](https://github.com/MoeMorox/mention),
version 3.0.1, which builds on the original
[paul999/mention](https://github.com/paul999/mention).
Original authors and contributors retain credit for their work. The extension
remains licensed under **GPL-2.0-only**; see [license.txt](license.txt).
This is an independent community fork, not an official phpBB extension.

## Features

- `@` autocomplete for users, with current-topic participants first.
- Debounced search that refreshes after deletion, cut and paste.
- Rounded mention tags with configurable background, text and font style.
- Live ACP colour swatches and tag preview.
- Separate user, group and large-group permissions.
- Recipient read-permission checks; inactive accounts and pending memberships excluded.
- Hard limits, server-side confirmation and per-account search rate limiting.
- Board notifications enabled by default; email is opt-in per user.
- ACP can allow user-selected email or block mention email globally.
- English and Spanish (`es` and `es_x_tu`) translations for the added functionality.
- Existing inherited translations remain available; newer options may fall back to English.

## Requirements

- phpBB 3.3.17 or newer in the 3.3 series.
- PHP 8.1 or newer. Automated tests target PHP 8.1 and 8.2.
- Prosilver or a compatible style exposing the phpBB template events.

## Installation and upgrade

1. Back up the database and extension files.
2. Download a release package and extract into `ext/paul999/mention`.
3. Enable the extension under ACP → Customise → Manage extensions.
4. Assign mention permissions under ACP → Permissions → Group permissions.
5. Configure appearance and notification channels under ACP → Extensions → Mentions.

The original namespace and installation directory are deliberately retained for
upgrade compatibility. Do not install this alongside another `paul999/mention`
version. For an upgrade, disable the existing extension, replace its files,
enable it to apply migrations, and purge the cache. Do not delete extension data.

Users choose notification methods under UCP → Board preferences → Edit notification
options. Allowing email in ACP does not subscribe users automatically. Previewing
and mentioning yourself do not generate mention notifications. Pending posts wait
for approval; editing does not immediately send new mention notifications.

The editor remains a plain textarea: `[smention]` BBCode is visible while editing
and renders as a tag in previews and published posts.

## Safety and testing

See [HARDENING.md](HARDENING.md) for limits, isolated test instructions, behaviour
and validation boundaries. Automated tests use synthetic users and an isolated
database, without sending mail. Review group permissions and test on a staging
forum before updating a live installation.
