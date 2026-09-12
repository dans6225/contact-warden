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

(reads `DB_HOST`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD`/`DB_PORT`/`DB_CHARSET` from `.env` —
see `.env.example`.)

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

## Testing

```bash
composer install
vendor/bin/phpunit
```

The suite is entirely SQLite-backed — no external database required.

## License

MIT — see [LICENSE](LICENSE).
