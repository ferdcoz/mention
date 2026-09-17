# Security controls and validation

Maintainer: Fernando Coz. This fork retains the original GPL license and credits.

## Controls

- Autocomplete requires an authenticated non-bot account with `u_can_mention`.
- Users, groups and large groups have separate permissions checked on the server.
- Topic-participant priority requires forum read permission and visible topic/posts.
- Recipients must be active normal/founder accounts with forum read permission.
- Hidden groups, bots, guests and pending memberships are excluded.
- Each post is bounded to 500 unique candidates and 500 mention tags.
- Mass threshold is configurable from 1 to 50, default 50. Aggregate recipient
  count also requires the large-group permission and confirmation.
- Confirmation binds the message, forum, count and session. Pending posts store a
  receipt; approval revalidates the author and cannot grow the confirmed count.
- Search budget: 30 requests per account per 10-second fixed window. Boundary
  bursts can reach 60 requests. This does not replace phpBB posting flood controls.
- Result count is bounded to 50; read marking uses keyset batches of 250.
- Menu labels are escaped, colours are validated, ACP changes use phpBB CSRF tokens.
- Board/email preferences are respected; disabling mention email globally filters
  its subscription method and makes the email template unavailable.
- Deleting posts removes associated mention notifications through phpBB's manager.

## Behaviour and limitations

Self mentions do not notify. Previews do not notify. Pending posts wait for approval.
Edits retain upstream behaviour, without immediate new mention notifications.
Permissions target users/groups/large groups, not an author-to-recipient-group matrix.
Only posting contexts enable mention BBCode. Email requires a working phpBB mail transport.
With automatic background, the ACP preview uses a reference colour; the forum's
active style determines its final colour. Prosilver uses #536482 as fallback.
Existing explicit user preferences are preserved; defaults are board on/email off.
Consent receipts record message and count, not a permanent recipient list.

## Isolated automated tests

Never run the PHP suite against a real forum database. It creates and drops `test_*`
tables in the disposable database `mention_test` on 127.0.0.1:3306, root with an
empty password. The defaults are intended for a network-isolated test container.

Set `MENTION_CORE_PATH` to the exact phpBB source directory (with trailing slash)
and `MENTION_VENDOR_AUTOLOAD` to its Composer autoload file. Test with phpBB
3.3.17 commit `3508484fdc18cd97eeab229da830055c79fcc59e` and installed dependencies.

```sh
php -d error_reporting=24575 hardening/tests-preferences.php
node hardening/tests-js.cjs
```

The suite exercises real phpBB ACL, database, parser and preference resolution,
with intercepted notification/log services. Synthetic fixtures cover private forum
access, memberships, 500/1000/5000-member groups, confirmation, pending approval,
topic priority, 5001-topic read marking, and 60 concurrent search requests.
It does not itself prove real SMTP delivery or browser/style compatibility.
GitHub Actions runs PHP 8.1/8.2 against MySQL 5.7 and MariaDB 10.11.

## Updates and rollback

Track upstream changes in a separate branch; do not replace this fork with upstream
files without reviewing the controls. Retain applied migration names and add new
migrations when changing configuration/schema. Repeat tests and functional checks.
Before deployment prepare recoverable database/file backups and confirm the actual
phpBB/PHP runtime. To roll back, disable the extension and purge cache, preserving
data. Restore the previous compatible package; use a consistent database backup
when needed. Do not use Delete data as a quick rollback.
