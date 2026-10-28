# 4. Mapping to a security standard

Standard: **OWASP Top 10 Proactive Controls 2024** (OWASP, 2024). It fits a developer-built web
application best: it is written as "what the developer must do", while CIS Controls and CCM target an
organisation or cloud provider and IEC 62443 targets industrial systems. For individual checks the
controls point to the **OWASP ASVS 5.0**, which is cited where useful.

| Control | What the standard asks | Implemented in the vault | Coverage |
|---|---|---|---|
| **C1 Implement Access Control** | Deny by default, check on every request, least privilege, no IDOR | `auth` on every non-guest route, owner-only policies (404), queries scoped to the user, admin middleware, admin cannot read content, role not mass-assignable | ●●● full |
| **C2 Use Cryptography to Protect Data** | Strong algorithms, protect data at rest and in transit, key management | AES-256-GCM for DB fields and files, separate keys, Argon2id passwords, HSTS + Secure cookies; *keys in .env, no KMS/HSM, no key rotation procedure yet* | ●●○ most |
| **C3 Validate all Input & Handle Exceptions** | Allow-list validation, length + format, syntax and semantics, safe exception handling | Form Requests with regex + length on every field, file content check, config validated at boot, generic error pages, exceptions logged with request id, fail closed on integrity errors | ●●● full |
| **C4 Address Security from the Start** | Threat modelling, secure design, security requirements | This threat model, misuse cases, design before code, tests per misuse case | ●●● full |
| **C5 Secure By Default Configurations** | Secure defaults, hardening, no debug in production | Debug off, session encrypted / Secure / Strict / 30 min, Argon2id default, `role = user`, `is_active` checked every request, `.npmrc ignore-scripts`, production config refuses insecure values | ●●● full |
| **C6 Keep your Components Secure** | Inventory, update, vulnerability monitoring, trusted sources | Lock files, Dependabot (Composer, npm, Actions), `composer audit`, `npm audit`, SHA-pinned actions; *no SBOM published yet* | ●●○ most |
| **C7 Secure Digital Identities** | Strong password policy, MFA, secure session management, brute-force protection | 12–128 chars, breached-password check, Argon2id, lockout, session regeneration, logout invalidates; ***no MFA and no password reset flow*** | ●●○ partial |
| **C8 Leverage Browser Security Features** | CSP, HSTS, cookie flags, framing protection | Nonce-based CSP without `unsafe-inline`, HSTS, nosniff, DENY framing, no-referrer, Permissions-Policy, COOP | ●●● full |
| **C9 Implement Security Logging and Monitoring** | Log security events, protect logs, alerting | JSON security log with 20+ event types, request correlation, redaction, 90-day retention, admin viewer; ***no central SIEM, no alerting, logs on the same host*** | ●●○ partial |
| **C10 Stop Server Side Request Forgery** | Validate outbound requests, allow-list destinations | Only one outbound call, to a fixed URL (Pwned Passwords) with no user-controlled part | ●●● full (by design) |

**Overall:** 6 of 10 controls fully covered, 4 mostly or partially.

## What else the standard would add (improvements)

1. **MFA (C7).** TOTP via Laravel Fortify, or WebAuthn passkeys. This is the strongest remaining
   countermeasure against R1 and phishing (attack 1.3), which the current design can't stop.
2. **Password reset and e-mail verification (C7),** with single-use tokens that expire, and a generic
   "if the account exists we sent an e-mail" message.
3. **Central logging and alerting (C9).** Ship `security-*.log` to an append-only store the app can't
   delete from (syslog/rsyslog over TLS, Loki, Elastic), and alert on `auth.lockout`, a burst of
   `authz.denied` from one user, and any `crypto.*.integrity_failed`.
4. **Key management (C2).** Keep keys in a secret manager (Vault, AWS KMS, Azure Key Vault), use envelope
   encryption (a per-file data key wrapped by a master key), and add a rotation procedure. Laravel supports
   `APP_PREVIOUS_KEYS` for this.
5. **SBOM and signed releases (C6).** Generate a CycloneDX SBOM in CI, and sign build artefacts with
   Sigstore / GitHub artifact attestations.
6. **ASVS level 2 verification.** Go through the ASVS checklist chapter by chapter and record the gaps.
