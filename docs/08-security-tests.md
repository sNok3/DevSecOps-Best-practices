# 8. Security tests

Every misuse case becomes one or more test cases: *given* the attacker's action, *expect* it to be
blocked **and** to leave a trace in the security log. The tests run offline with
`php artisan test` (82 tests) and in CI on every pull request (`.github/workflows/ci.yml`).

| Misuse case | Test case (input → expected result) | Test |
|---|---|---|
| MC-01 brute force | 6 logins with wrong passwords → 6th refused even with the right password; `auth.lockout` at level ALERT | `AuthenticationTest::test_account_locks_after_five_failed_attempts_and_lockout_is_logged` |
| MC-01 session fixation | Session id before login ≠ after login | `…::test_successful_login_regenerates_the_session_and_is_logged` |
| MC-01 weak password | `Short1!` → rejected; breached passphrase (fake API) → rejected | `…::test_short_password_is_rejected`, `…::test_breached_password_is_rejected` |
| MC-01 disabled account | Disabled user logs in → refused; existing session → logged out | `…::test_disabled_account_cannot_log_in`, `…::test_disabled_account_loses_its_existing_session` |
| MC-02 enumeration | Wrong password vs unknown e-mail → identical message | `…::test_wrong_password_gives_the_same_generic_error_as_an_unknown_account` |
| MC-03 role escalation | POST `/register` with `role=admin` → role stays `user` | `…::test_role_cannot_be_set_through_the_registration_form` |
| MC-04 IDOR notes | Mallory GET/PUT/DELETE Alice's note → 404 ×3, note unchanged, 3× `authz.denied` with Mallory's id | `NoteTest::test_other_users_note_is_not_found_and_the_attempt_is_logged` |
| MC-04 mass assignment | POST note with `user_id` of Bob → stored for Alice | `NoteTest::test_user_id_cannot_be_injected_to_create_a_note_for_someone_else` |
| MC-05 stored XSS | Note body `<script>alert(document.cookie)</script>` → HTML contains only `<script`; title with `<img onerror>` → rejected | `NoteTest::test_script_in_a_note_is_never_returned_as_markup`, `…::test_html_in_the_title_and_control_characters_in_the_body_are_rejected` |
| MC-06 SQL injection | `/notes/1 OR 1=1` → 404 | `NoteTest::test_sql_injection_in_the_id_is_harmless` |
| MC-06 DB dump | Raw `notes` row contains no plaintext, has a GCM tag | `NoteTest::test_note_is_encrypted_in_the_database` |
| MC-07 web shell | `shell.php` → rejected; PHP code named `cat.png` → rejected (content check); real PNG → accepted | `FileTest::test_php_file_is_rejected`, `…::test_php_code_disguised_as_png_is_rejected_by_content_check`, `…::test_real_png_is_accepted` |
| MC-07 path traversal | Name `../../../public/evil.txt` → stored as `evil.txt`, only `vault/<uuid>.bin` on disk | `FileTest::test_path_in_the_file_name_is_stripped_and_never_used_on_disk` |
| MC-07 flooding | File > limit → rejected; 11th upload in a minute → 429 + `abuse.rate_limited` | `FileTest::test_too_large_file_is_rejected`, `…::test_upload_rate_limit` |
| MC-08 IDOR files | Mallory downloads/deletes Alice's file → 404 ×2, logged | `FileTest::test_other_users_file_is_not_found_and_logged` |
| MC-09 tampering | Flip one bit in the stored blob → 500, `crypto.file.integrity_failed` ERROR; moved ciphertext / wrong key → rejected | `FileTest::test_tampered_file_fails_closed_and_logs_an_integrity_error`, `FileCryptoTest` (8 tests) |
| MC-10 admin functions | Normal user → 403 ×3 + `authz.admin.denied`; admin reads a user's note → 404; admin disables self → refused | `AdminTest`, `NoteTest::test_admin_cannot_read_user_notes` |
| MC-11 browser | CSP with nonce and without `unsafe-inline`, `frame-ancestors 'none'`, nosniff, HSTS on https; cookie Secure+HttpOnly+Strict | `HardeningTest::test_security_headers_are_set`, `…::test_hsts_on_https`, `…::test_session_cookie_is_secure_httponly_and_strict` |
| MC-12 error leak | Exception "DB password is hunter2" with debug off → page shows only the request id | `HardeningTest::test_unexpected_exception_shows_a_generic_page_with_a_reference_only` |
| MC-12 log forging | E-mail with `\n{fake entry}` → still exactly one log line | `SecurityLogTest::test_newline_in_input_cannot_forge_a_second_entry` |
| MC-13 CSRF | POST without token → not stored, `input.csrf.rejected` | `HardeningTest::test_request_without_csrf_token_is_rejected_and_logged` |
| MC-14 secrets | Password never in the security log; redaction at any depth; insecure config (null debug, short key, same keys, http) → rejected | `AuthenticationTest::test_the_password_never_reaches_the_security_log`, `SecurityLogRedactTest`, `ConfigValidatorTest` |
| MC-14 secret scanning | Canary file with a fake AWS key must be detected, otherwise CI fails | `secret-scan.yml` canary step |
| MC-15 dependencies | `composer audit --abandoned=fail` and `npm audit` fail CI on a known vulnerability | `ci.yml`; manual: in a branch, pin a package to a version with a published advisory (look one up in the GitHub Advisory Database) → audit fails |
| MC-16 pipeline | Workflow with `${{ github.event.issue.title }}` in `run:` → zizmor fails; direct push to `main` → rejected by ruleset | `workflow-lint.yml`; manual, see `09-github-setup.md` |
| Input regexes | 21 accept/reject examples | `InputRulesTest` |

## Manual / behavioural tests (with evidence)

These need the running app or GitHub and are recorded with a screenshot or log excerpt:

1. **Browser CSP:** inject `<script>alert(1)</script>` in DevTools on a page → the console reports
   "Refused to execute inline script…".
2. **Security log:** fail 6 logins, then as admin open *Security log* → `auth.login.failed` ×5 + `auth.lockout`.
3. **Encrypted storage:** `sqlite3 database/database.sqlite "select body from notes limit 1"` → base64
   ciphertext; `xxd storage/app/private/vault/<uuid>.bin | head` → starts with `VF1`, no readable text.
4. **Config fail-closed:** set `APP_ENV=production APP_DEBUG=true` and open the site → it refuses to start
   (500, "Insecure configuration" in `laravel.log`); `php artisan vault:check-config` → exit 1.
5. **GitHub controls:** see the validation table in `09-github-setup.md`.
