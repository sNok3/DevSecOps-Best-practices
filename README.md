# DevSecOps Vault

A small, deliberately security-focused web application: users keep private **notes** and **files**, and
both are encrypted at rest. It is built to demonstrate secure coding, security logging, cryptography and
a hardened GitHub repository and pipeline.

Stack: Laravel 13 (PHP 8.3+), React 19 + Inertia.js 3, Vite 8, SQLite (default).

The security design (threat model, attack trees, risk matrix, misuse cases, standard mapping, logging,
crypto, tests) is in [`docs/`](docs/README.md).

## Run it locally

Requirements: PHP 8.3+ with `pdo_sqlite`, `openssl` and `mbstring`; Composer; Node 20+ (or Laravel Herd,
which includes all of these).

```bash
composer install
npm ci                                   # .npmrc: install scripts disabled, audit on
cp .env.example .env
php artisan key:generate                 # APP_KEY
php artisan vault:key                    # prints VAULT_FILE_KEY=...  -> paste it into .env
# local only: set APP_DEBUG=true in .env if you want stack traces
php artisan vault:check-config           # must say "Configuration OK."
touch database/database.sqlite
php artisan migrate
ADMIN_EMAIL=you@example.com ADMIN_PASSWORD='a long admin passphrase' php artisan db:seed
npm run build                            # or `npm run dev` in a second terminal while developing
php artisan serve                        # http://localhost:8000
```

The session cookie is `Secure`. Browsers accept that on `http://localhost`; anywhere else, use HTTPS.

## Tests

```bash
php artisan test        # 82 tests, one or more per misuse case (docs/08-security-tests.md)
composer audit          # known vulnerabilities in PHP dependencies
npm audit
vendor/bin/pint --test  # code style
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
