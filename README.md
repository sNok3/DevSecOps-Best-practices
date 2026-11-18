# DevSecOps Vault

A small, deliberately security-focused web application: users keep private **notes** and **files**, and
both are encrypted at rest. It is built to demonstrate secure coding, security logging, cryptography and
a hardened GitHub repository and pipeline.

Stack: Laravel 13 (PHP 8.4), React 19 + Inertia.js 3, Vite 8, MySQL 8.4 (Docker) or SQLite (without Docker).

The security design (threat model, attack trees, risk matrix, misuse cases, standard mapping, logging,
crypto, tests) is in [`docs/`](docs/README.md).

## Run it with Docker

Requirements: Docker with Compose v2. Two containers start: `app` (FrankenPHP: Caddy + PHP 8.4) and `db`
(MySQL 8.4).

```bash
cp .env.example .env
php -r 'echo "base64:".base64_encode(random_bytes(32)), PHP_EOL;'   # run twice: APP_KEY and VAULT_FILE_KEY
php -r 'echo bin2hex(random_bytes(24)), PHP_EOL;'                    # DB_PASSWORD
# paste the three values into .env (the two keys must differ)
docker compose up -d --build            # http://localhost:8000
docker compose exec -e ADMIN_EMAIL=you@example.com -e ADMIN_PASSWORD='a long admin passphrase' app php artisan db:seed --force
```

| Command | What it does |
|---|---|
| `docker compose up -d --build` | Build and start (or update) both containers |
| `docker compose logs -f app` | Follow the application log (the security log is also in `storage/logs/`) |
| `docker compose exec app php artisan test` | Run the test suite inside the app container |
| `docker compose down` | Stop; data stays in the `storage` and `dbdata` volumes |
| `docker compose down -v` | Stop and **delete all data** |

`.env` is the single source of truth: the app reads it, and the MySQL container is created from the same
`DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`. MySQL only reads them when the `dbdata` volume is created, so
to change them later, change them in MySQL too or start over with `docker compose down -v`. Never change
`APP_KEY` or `VAULT_FILE_KEY` once there is data: notes and files encrypted with the old key cannot be read
with a new one. Back up `.env` together with the volumes.

The app container refuses to start when `vault:check-config` fails, waits for MySQL to be healthy, runs
the migrations and keeps the encrypted files and logs in the `storage` volume. It runs as a non-root user
with a read-only root file system, no Linux capabilities and `no-new-privileges`, and its port is bound to
`127.0.0.1` only. MySQL keeps its data in the `dbdata` volume, has no published port (only the app can
reach it) and a random root password that is never used. Base images are pinned by digest and updated by
Dependabot.

The session cookie is `Secure`. Browsers accept that on `http://localhost`; anywhere else, put HTTPS in
front and set `APP_ENV=production` and an `https://` `APP_URL` (the app refuses to start otherwise).

## Tests

```bash
docker compose exec app php artisan test                     # 88 tests, one or more per misuse case (docs/08-security-tests.md)
docker compose exec app php artisan test --filter=NoteTest   # one test class
```

The tests never touch the running application's data, even inside its container: `phpunit.xml` forces an
in-memory SQLite database, each run gets its own temporary storage folder (files and security log), and
`tests/TestCase.php` refuses to start on any other database.

The pipeline also runs:

```bash
composer audit                        # known vulnerabilities in PHP dependencies
npm audit --omit=dev                  # known vulnerabilities in what ships to the browser
vendor/bin/pint --test                # code style
```

## Run it without Docker

Requirements: PHP 8.3+ with `pdo_sqlite`, `openssl` and `mbstring`; Composer; Node 20+ (or Laravel Herd,
which includes all of these).

```bash
composer install
npm ci                                   # .npmrc: install scripts disabled, audit on
cp .env.example .env                     # then set DB_CONNECTION=sqlite and remove the other DB_ lines
php artisan key:generate                 # APP_KEY
php artisan vault:key                    # prints VAULT_FILE_KEY=...  -> paste it into .env
# local only: set APP_DEBUG=true in .env if you want stack traces
php artisan vault:check-config           # must say "Configuration OK."
touch database/database.sqlite
php artisan migrate
ADMIN_EMAIL=you@example.com ADMIN_PASSWORD='a long admin passphrase' php artisan db:seed
npm run build                            # or `npm run dev` in a second terminal while developing
php artisan serve                        # http://localhost:8000
php artisan test
```

## Security features at a glance

| Area | Implementation |
|---|---|
| Passwords | Argon2id (64 MiB, t=4), 12–128 chars, breached-password check (k-anonymity), rehash on login |
| Login | 5 attempts/min per e-mail+IP then lockout, generic errors, equal timing, session regeneration |
| Access control | Owner-only policies (404 for others), admin cannot read content, role not mass-assignable |
| Input | Form Requests with allow-list regexes + lengths, file type by content, config validated at boot |
| Encryption | AES-256-GCM: DB columns (`APP_KEY`), files with AAD + SHA-256 check (separate `VAULT_FILE_KEY`) |
| Browser | Nonce-based CSP, HSTS, frame denial, nosniff, Secure/HttpOnly/SameSite=Strict cookies |
| Errors | Generic pages with a request id only; fail closed |
| Logging | JSON security log (`storage/logs/security-*.log`), 20+ event types, redaction, request correlation, admin viewer |
| Repository | SHA-pinned actions, read-only tokens, gitleaks + canary, Semgrep, zizmor, audits, Dependabot, CODEOWNERS |

## Security

See [SECURITY.md](SECURITY.md) to report a vulnerability.
