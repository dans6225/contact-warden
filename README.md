# ContactWarden

A portable, framework-agnostic PHP package for contact-form abuse detection — secure by
default, and installable in any PHP app.

Most anti-spam approaches are a single gate: one check, pass or fail. ContactWarden scores every
submission across several independent signals and only acts once the evidence adds up, so:

- **No single weak signal ever hard-fails a legitimate person.** A fast fill time, a missing
  token, an unfamiliar referer — none of these alone reach REJECT.
- **A cluster of strong signals rejects confidently.** A filled honeypot, a forged token, and a
  rate-limit breach together are unambiguous.
- **Nothing is a permanent ban.** IP/netblock reputation decays over time (half-life, not a
  blocklist), so shared IPs (CGNAT, corporate egress) recover instead of staying poisoned by one
  abuser.
- **Failure is graceful.** A visitor with JavaScript disabled gets challenged, not silently
  locked out — that trade-off is a config choice, not an accident of implementation.

## How it works

1. **Form load** — your app issues a signed, expiring, single-use token and a randomized
   field-name map (defeats bots that scrape static field names).
2. **Submission** — `Engine::handle()` runs every configured signal, sums their weighted
   contributions, and produces a `Decision`: `ACCEPT`, `CHALLENGE`, or `REJECT`.
3. **You act on the decision** — send the mail on `ACCEPT`, run your own secondary verification
   step on `CHALLENGE` (a CAPTCHA hook is provided, unimplemented by default), log and apply a
   reputation penalty on `REJECT`.

Included signals: honeypot, server-authoritative timing, token validity, referer, user-agent
(deliberately conservative — it won't flag privacy-hardened browsers), decaying IP reputation,
spam-keyword/link-density content heuristics, and sliding-window rate limiting.

A few optional building blocks are included for common needs beyond scoring itself:
`Validation\RequiredFieldValidator` for pre-scoring field checks, `Store\ContactRecordStore` (with
a `PdoContactRecordStore` default) for keeping the full content of accepted submissions somewhere
reviewable, and `Mail\ConditionalMailer` for toggling email delivery and/or storage independently
— see [Optional building blocks](#optional-building-blocks) below.

## Installation

```bash
composer require dans6225/contact-warden
```

ContactWarden ships with PDO-based storage (MySQL or SQLite — SQLite keeps the test suite and
small installs dependency-free) and plain `.sql` migrations, no framework migration system
required:

```bash
php vendor/dans6225/contact-warden/bin/migrate.php
```

(reads `DB_HOST`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD`/`DB_PORT`/`DB_CHARSET` from real
environment variables first, then from a `.env` in the directory you run it from — see
`.env.example`. It's idempotent, so re-running it after an upgrade is safe.)

## Quickstart

```php
use ContactWarden\Config\ContactWardenConfig;
use ContactWarden\Engine;
use ContactWarden\Http\RequestContext;
use ContactWarden\Mail\CallbackMailer;
use ContactWarden\Reputation\DecayingScore;
use ContactWarden\Reputation\StorageBackedReputationStore;
use ContactWarden\Scoring\ScoreEngine;
use ContactWarden\Signals\HoneypotSignal;
use ContactWarden\Signals\TimingSignal;
use ContactWarden\Signals\TokenValiditySignal;
use ContactWarden\Store\DatabaseConfig;
use ContactWarden\Store\PdoStorage;
use ContactWarden\Token\TokenIssuer;

$config = ContactWardenConfig::default();
$storage = PdoStorage::fromConfig(DatabaseConfig::fromEnv());
$reputation = new StorageBackedReputationStore($storage, new DecayingScore($config->reputationHalfLifeSeconds));
$tokenIssuer = new TokenIssuer($_ENV['CW_TOKEN_SECRET'], $storage, $config->tokenExpirySeconds);

$engine = new Engine(
    $config,
    signals: [
        new HoneypotSignal('website', $config->weights['honeypot']),
        new TimingSignal($config->minFormFillSeconds, $config->weights['timing_too_fast']),
        new TokenValiditySignal($config->weights['token_missing'], $config->weights['token_invalid'], $config->weights['token_expired']),
        // ...add the rest of the signals you want; see examples/plain-php/bootstrap.php for the full set
    ],
    scoreEngine: new ScoreEngine($config),
    storage: $storage,
    reputationStore: $reputation,
    tokenIssuer: $tokenIssuer,
    mailer: new CallbackMailer(fn (array $data) => mail($_ENV['NOTIFY_TO'], 'New contact submission', print_r($data, true))),
);

$decision = $engine->handle(RequestContext::fromGlobals($_SERVER, $_POST));
```

A complete, working demo (form + init endpoint + submit handler) lives in
[`examples/plain-php/`](examples/plain-php/).

## Configuration

Every scoring weight, threshold, and behavior is a constructor argument on `ContactWardenConfig`
— see [`src/Config/ContactWardenConfig.php`](src/Config/ContactWardenConfig.php) for the full list
and the reasoning behind each default.

## Optional building blocks

**Field validation before scoring.** A submission can be well-formed and still get REJECTed by
the Engine, or malformed and never reach it at all — run required-field checks first:

```php
use ContactWarden\Validation\RequiredFieldValidator;

$errors = RequiredFieldValidator::validate($fields, requiredFields: ['name', 'email', 'message']);
if ($errors !== []) {
    // re-render the form with $errors and the previously entered values — nothing has
    // been scored or has consumed a token yet
}
```

**Keeping the actual message content.** `StorageInterface::logSubmission()` only ever records the
scoring decision, never the message — by design, since abuse evidence and message content are
different concerns with different retention needs. If you want accepted submissions kept
somewhere a human can review them, add a `ContactRecordStore`:

```php
use ContactWarden\Mail\ConditionalMailer;
use ContactWarden\Store\PdoContactRecordStore;

$recordStore = PdoContactRecordStore::fromConfig(DatabaseConfig::fromEnv()); // same DB as $storage, or a different one
$mailer = new ConditionalMailer(
    mailer: $yourRealMailer,
    recordStore: $recordStore,
    deliverEmail: true,
    deliverDatabase: true,
);
// pass $mailer to Engine as usual
```

Run `bin/migrate.php` again after adding `PdoContactRecordStore` for the first time — it creates
`cw_contacts` alongside the base tables. `ContactRecordStore` is deliberately storage-only: no
read-back, no read/archived/deleted state. Building an inbox on top (as opposed to just keeping a
record) is host-app territory — the shape of that is too opinionated to bake into an abuse-detection
package.

## Admin UI connectors

ContactWarden has no admin UI of its own — but it does expose the read/write surface one needs,
under `ContactWarden\Admin\`, so a framework-specific **connector** package can build one without
reaching into storage internals:

- **`AdminDataSource`** (default: `Store\PdoAdminDataSource`) — read-only, paginated queries over
  submissions, abuse events, reputation, and token stats. Deliberately separate from
  `StorageInterface`: that one is what `Engine` itself writes through, and every implementer
  (including the test suite's SQLite backend) has to satisfy it, so listing/pagination for an
  optional admin UI has no business living there.
- **`AdminMaintenance`** (default: `Store\PdoAdminMaintenance`) — the housekeeping writes an admin
  screen needs that `Engine` never performs itself: purge expired tokens, purge old
  submissions/abuse-log rows by age, forget one subject's reputation, or reset all of it.
- **`AdminConnectorInterface`** — the contract a per-framework connector package implements: five
  `render*()` methods (returning plain HTML, so this package never depends on any framework's HTTP
  types) plus a generic `handleAction()` dispatch for the `AdminMaintenance` operations above.
- **`MaintenanceActions`** — the standard action vocabulary (`purge_tokens`, `purge_submissions`,
  `purge_abuse`, `forget_reputation`, `reset_reputation`) implemented once, so a connector's
  `handleAction()` is normally a one-line delegation rather than its own copy of the mapping.

Connectors so far:
[`contact-warden-ci4`](https://github.com/dans6225/contact-warden-ci4) (CodeIgniter 4),
[`contact-warden-laravel`](https://github.com/dans6225/contact-warden-laravel), and
[`contact-warden-plain-php`](https://github.com/dans6225/contact-warden-plain-php) (no framework).
Building one for another framework means implementing `AdminConnectorInterface` against
`AdminDataSource`/`AdminMaintenance` the same way — the plain-PHP connector is one small class and
the best reference to work from.

Two things this deliberately does **not** cover, same reasoning as elsewhere in this README:
message content (`ContactRecordStore` stays write-only — see above) and persisting
admin-configurable settings (see [Runtime-configurable settings](#runtime-configurable-settings)
below) — both stay host-app territory.

## Runtime-configurable settings

`ContactWardenConfig`'s constructor already accepts every weight/threshold as a named argument, so
letting an admin change them without a deploy doesn't need any package support — just rebuild the
config from whatever your app already uses for admin-managed settings on each request (or cache it
per-request):

```php
$config = new ContactWardenConfig(
    challengeThreshold: (int) $yourSettingsStore->get('ContactWarden.challengeThreshold', 30),
    rejectThreshold: (int) $yourSettingsStore->get('ContactWarden.rejectThreshold', 70),
    // ...override only what you expose in your admin UI; everything else stays at its default
);
```

## Testing

```bash
composer install
vendor/bin/phpunit
```

The suite is entirely SQLite-backed — no external database required.

## License

MIT — see [LICENSE](LICENSE).
