# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

ContactWarden — a portable, framework-agnostic PHP package for contact-form abuse detection
(`dans6225/contact-warden`, MIT, PHP `^8.1`). It's the public relaunch of an internal prototype
(`contact-worx`, kept elsewhere as an internal reference and not to be confused with this repo —
this repo has its own fresh git history). See `README.md` for the user-facing pitch and quickstart;
this file is about working *in* the codebase.

## Commands

- Install dependencies: `composer install`
- Run the full test suite: `vendor/bin/phpunit`
- Run a single test: `vendor/bin/phpunit --filter testMethodName` or `vendor/bin/phpunit path/to/FileTest.php`
- Apply the package's own DB migrations (plain `.sql` files, no framework migration system):
  `php bin/migrate.php` — reads `DB_HOST`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD`/`DB_PORT`/
  `DB_CHARSET` from `.env` (see `.env.example`); it globs `src/Store/migrations/*.sql` in filename
  order, so a new migration just needs the next `NNNN_` prefix to be picked up automatically.
- The test suite is entirely SQLite-backed (`tests/unit/Support/SqliteStorageFactory.php` builds an
  in-memory schema mirroring the real migrations) — it never touches a real database, even though
  `.env` here is configured against a live MySQL instance for manual/example testing.

## Architecture

**Pipeline**: `Engine::handle(RequestContext $context): Decision` is the orchestrator. It first
resolves the submission's token (`resolveTokenAttributes()`), which sets `token_status` and — only
when valid — merges the token's field-map-resolved values into `$context->body` and attaches
`form_started_at`/`reported_interaction_count` to `$context->attributes`. Every configured
`SignalInterface` then evaluates that same `RequestContext` independently, `ScoreEngine` sums their
weighted `SignalResult`s and compares against `ContactWardenConfig`'s thresholds to produce a
`Decision` (`ACCEPT`/`CHALLENGE`/`REJECT`), and `Engine::act()` logs the submission, mails on
ACCEPT, and logs abuse + applies a reputation penalty on CHALLENGE/REJECT. Signals never talk to
each other — they only read `RequestContext`, which is why new signals are cheap to add and easy to
unit-test in isolation.

**Config is a plain immutable value object, by design.** `ContactWardenConfig`'s constructor takes
every weight/threshold as a named argument with a sensible default (see the class's own docblock
for the reasoning behind each default — it encodes real design invariants, e.g. only the honeypot
signal is strong enough to REJECT alone, and a missing token lands in CHALLENGE, never REJECT, so
no-JS users aren't hard-blocked). There is deliberately no admin-settings/override machinery in the
package itself: a host app that wants runtime-configurable settings just rebuilds
`ContactWardenConfig` per request from whatever it already uses for that (see README's "Runtime-
configurable settings" section) — don't add a settings abstraction here, it's already supported.

**Token lifecycle**: `TokenIssuer::issue()` HMAC-signs a random id (server secret, never a
client-derivable value) and hands the field map + IP to `TokenStore::storeToken()`.
`validateAndConsume()` returns a `TokenConsumptionResult` whose `status` is one of
`valid`/`invalid`/`expired`/`missing` — `PdoStorage::consumeToken()` distinguishes these by
querying `consumed_at`/`expires_at` before marking a row consumed, which is what lets
`TokenValiditySignal` weight each case differently instead of collapsing them all into "invalid".

**Storage is split by concern, not merged into one god-interface**: `StorageInterface` (tokens,
scoring decisions, abuse evidence, decaying reputation — everything the Engine itself needs) is
required; `ContactRecordStore` (full accepted-submission content, e.g. for an admin inbox) is a
separate, optional interface with its own default `PdoContactRecordStore` — a consumer that doesn't
need message content kept anywhere just never wires one in. Same pattern for mail:
`MailerInterface` is the only thing `Engine` depends on, and `ConditionalMailer` is an optional
decorator (toggles email/storage independently) rather than a special case inside `Engine`.

**`PdoStorage::setReputation()` is deliberately not an atomic upsert** — it's portable
exists-check-then-branch SQL rather than MySQL's `ON DUPLICATE KEY UPDATE` (which SQLite doesn't
support), because the test suite needs to run against SQLite. The tradeoff (a small TOCTOU race) is
documented inline and accepted because this isn't a high-concurrency-per-subject path.

**Field obfuscation is a one-shot server-side swap, not a live rename.** `FieldMap::generate()`
produces real-name → randomized-name pairs at token-issuance time; the HTML ships with real
(default) field names, and `js/contact-warden.js` renames the actual DOM inputs at runtime after
fetching the map from an init endpoint. A submission with no token/no renamed fields means JS never
ran — that's the no-JS fallback path, not an error case, and `Engine` already handles it (raw body
used as-is, `token_status = missing`).

**`examples/plain-php/`** is a complete, working reference integration (form + init endpoint +
submit handler) — read it before wiring the package into a new host app; it shows the exact
signal-construction pattern `Engine` expects.

**Admin surface** (`src/Admin/`): a read/write contract for admin UIs, deliberately separate from
`StorageInterface` (which `Engine` writes through). `AdminDataSource` (+ `Store\PdoAdminDataSource`)
is read-only and paginated; `AdminMaintenance` (+ `Store\PdoAdminMaintenance`) holds the purge/
forget/reset writes; `MaintenanceActions` is the one implementation of the action-name vocabulary
that connectors delegate `handleAction()` to; `AdminConnectorInterface` is what a per-framework
connector package implements (five `render*()` methods returning HTML strings, plus
`handleAction()`). This package renders nothing itself — rendering lives in the connector packages
(`contact-warden-ci4`, `contact-warden-laravel`, `contact-warden-plain-php`, each its own repo).
The plain-PHP one is the smallest reference for writing another.

## Known consumer

danoladigital (a separate CodeIgniter 4 project) depends on this package via a local Composer path
repository (mirrored copy, `dev-main`) and has its own admin UI (settings/contacts/system tabs). Its
System tab reads and writes through `AdminDataSource`/`AdminMaintenance`; Settings and Contacts
stay host-app code, since this package deliberately has no settings machinery and
`ContactRecordStore` is write-only. Because the mirror is a copy, run `composer update
dans6225/contact-warden` there after changing this package — and if production deploys `vendor/`
by hand, verify `vendor/composer/autoload_psr4.php` actually got updated.
