# 6. Logging

## 6.1 Concepts (BoK)

A **log** is a time-ordered record of events produced by a system. *Security* logging has two jobs:

* **Monitoring / detection:** notice an attack while it is happening (many failed logins, a burst of
  access-denied errors).
* **Forensics / accountability:** after an incident, reconstruct *who did what, when, from where, with
  what result*. **Forensic readiness** means the right events are already being recorded, in a reliable
  format, kept long enough, and protected, *before* anything happens (OWASP Logging Cheat Sheet; NIST SP 800-92).

Key aspects:

| Aspect | Meaning |
|---|---|
| Content: the "5 W's" | When (UTC timestamp), who (user id, IP, user agent), where (host, path, method), what (event type), outcome (success/failure) plus a correlation id |
| Level / severity | How urgent the event is (table 6.2) |
| Category | What kind of event it is (authentication, authorization, input, …) |
| Format | Plain text, syslog, JSON, CEF/LEEF, Windows Event XML (table 6.3) |
| Integrity and protection | Logs must not be editable by the application or attacker: separate file permissions, shipping to a central append-only store, hashing/signing |
| Retention | How long logs are kept: long enough for investigations (often 90 days hot, 1 year archived), short enough for privacy (GDPR data minimisation) |
| Time | Synchronised clocks (NTP), timestamps with timezone, so logs from different systems line up |
| Privacy and secrets | Never log passwords, tokens, keys, full card numbers or document contents; minimise personal data |
| Injection | User input in a log can forge entries (CR/LF) or attack the log viewer (HTML/JS). Encode it (JSON) or strip it |

## 6.2 Levels

Syslog (RFC 5424) defines eight severities. PSR-3 (PHP), Monolog and Laravel use the same names.

| Code | Level | Meaning | Example in the vault |
|---|---|---|---|
| 0 | emergency | System unusable | — |
| 1 | alert | Act immediately | `auth.lockout` (brute force in progress) |
| 2 | critical | Critical condition | — (reserved for e.g. key missing at runtime) |
| 3 | error | Error, something failed | `crypto.file.integrity_failed` |
| 4 | warning | Suspicious, not normal | `auth.login.failed`, `authz.denied`, `input.csrf.rejected` |
| 5 | notice | Normal but significant | `input.rejected`, `admin.user.disabled` |
| 6 | info | Normal operation | `auth.login.success`, `data.note.created` |
| 7 | debug | Developer detail | Never in production (`LOG_LEVEL=info`) |

## 6.3 Formats

| Format | Example | Notes |
|---|---|---|
| Plain text | `[2026-10-28 07:27:55] local.ERROR: File integrity check failed {"file_id":3}` | Readable, but hard to parse reliably |
| Syslog RFC 5424 | `<36>1 2026-10-28T07:27:55Z vault laravel 1234 authz - User 7 denied note 12` (`<36>` = facility 4 × 8 + severity 4) | Standard transport to a central server (UDP/TCP/TLS) |
| JSON lines | `{"message":"authz.denied","level_name":"WARNING","context":{...}}` | One object per line, machine-readable, injection-safe; used by the vault |
| CEF (ArcSight) / LEEF (QRadar) | `CEF:0\|Vault\|DevSecOps Vault\|1.0\|authz.denied\|Access denied\|5\|suser=7 src=10.0.0.5` | SIEM-specific key=value formats |
| Windows Event Log (EVTX/XML) | Event ID **4625** "An account failed to log on" in the *Security* channel | Structured, viewed in Event Viewer or `Get-WinEvent` |
| systemd journal | `journalctl -o json` → `{"MESSAGE":"...","PRIORITY":"4","_HOSTNAME":"...","SYSLOG_IDENTIFIER":"sudo"}` | Binary store with indexed fields |

## 6.4 Logging in different environments (examples)

**Linux (systemd journal)** on the development server, warnings and higher only:

```
$ journalctl -p warning -n 3 -o short-iso
2026-10-10T13:41:04+0300 fivemdev sudo[1456604]: snoke : user NOT in sudoers ; TTY=pts/0 ; PWD=/root ; USER=root ; COMMAND=/bin/bash
```

This is a real security event: an attempt to become root. Its category is *authorization*, its level
*warning*, and it records who, where, what and when. The same entry as JSON (`journalctl -o json`) has
fields such as `PRIORITY`, `_HOSTNAME`, `SYSLOG_IDENTIFIER`, `_EXE` and `__REALTIME_TIMESTAMP`. Other
useful commands: `journalctl -u ssh` (one service), `journalctl _COMM=sshd -g "Failed password"` (SSH
brute force), `last` / `lastb` (successful/failed logins), and `/var/log/auth.log` on systems with rsyslog.

**Windows:** Event Viewer → Windows Logs → **Security** (categories *Logon/Logoff*, *Account
Management*, *Object Access*, *Policy Change*), with IDs such as 4624 (logon), 4625 (failed logon),
4672 (special privileges), 4720 (account created) and 1102 (audit log cleared, a classic anti-forensics
sign). **Application** and **System** hold software and OS events. Check it with
`Get-WinEvent -FilterHashtable @{LogName='Security'; Id=4625} -MaxEvents 5`.

**Browser / web server:** Nginx access log (combined format: IP, time, request line, status, bytes,
referrer, user agent) and error log; the browser console shows CSP violations (`Refused to execute
inline script because it violates the following Content Security Policy directive…`).

**Application (the vault):** `storage/logs/laravel-YYYY-MM-DD.log` holds technical events and
exceptions, and `storage/logs/security-YYYY-MM-DD.log` holds security events as JSON.

## 6.5 Logging design for the vault

### Which events are needed (derived from the threat analysis)

| Category | Event | Level | Threat / misuse case it detects or explains |
|---|---|---|---|
| authentication | `auth.login.success` | info | baseline; login from new IP after many failures = stuffing succeeded (MC-01) |
| authentication | `auth.login.failed` (+ e-mail, `known_user`) | warning | brute force, stuffing, enumeration (MC-01, MC-02) |
| authentication | `auth.lockout` | alert | active brute force (MC-01) |
| authentication | `auth.logout`, `auth.registered` | info | timeline; mass registrations from one IP |
| authentication | `auth.session.revoked` | warning | disabled account still active (MC-10) |
| authorization | `authz.denied` (+ resource, id) | warning | IDOR probing: many denials from one user (MC-04, MC-08) |
| authorization | `authz.admin.denied` | warning | privilege escalation attempts (MC-10) |
| input_validation | `input.rejected` (form + failed rule names, **no values**) | notice | XSS/injection probing (MC-05, MC-07) |
| input_validation | `input.csrf.rejected` | warning | CSRF attempt or expired session (MC-13) |
| input_validation | `abuse.rate_limited` | warning | upload flood, automated abuse (MC-07) |
| data_access | `data.note.created/updated/deleted`, `data.file.uploaded/downloaded/deleted` | info | what data an attacker touched after a takeover (forensics) |
| crypto | `crypto.file.integrity_failed` | error | storage tampering or wrong key (MC-09) |
| crypto | `crypto.password.rehashed` | info | hash parameter upgrade (audit) |
| admin | `admin.user.enabled/disabled`, `admin.user.toggle_refused`, `admin.security_log.viewed` | notice / warning / info | insider actions, accountability (MC-10) |
| system | Unhandled exceptions → `laravel-*.log` with request id | error | failures, attack side effects (MC-12) |

### Entry format

```json
{
  "message": "authz.denied",
  "context": {
    "category": "authorization",
    "outcome": "failure",
    "request_id": "de3c994e-7103-4893-a05d-5da5e546e547",
    "user_id": 7,
    "ip": "10.0.0.5",
    "user_agent": "Mozilla/5.0 ...",
    "method": "GET",
    "path": "notes/12",
    "data": { "ability": "access", "resource": "Note", "resource_id": 12 }
  },
  "level": 300, "level_name": "WARNING", "channel": "production",
  "datetime": "2026-10-28T07:31:02.118+00:00", "extra": { "request_id": "de3c994e-..." }
}
```

### Rules in the implementation (`app/Support/SecurityLog.php`, `config/logging.php`)

* One entry point, `SecurityLog::event($event, $category, $data, $level)`, so every event has the same fields.
* JSON lines (Monolog `JsonFormatter`): user input is JSON-encoded, so a `\n` in it cannot start a fake entry.
* Request correlation: `AssignRequestId` puts a UUID in Laravel's `Context`. It appears in both logs, in
  the `X-Request-Id` response header and on the error page.
* Redaction: keys like `password`, `token`, `body` and `secret` are replaced with `[REDACTED]` at any
  depth. Validation failures log rule names, never the values.
* Separate channel and file, daily rotation, `LOG_SECURITY_DAYS=90` retention, file mode `0640`.
* Monitoring: admins see today's events on `/admin/security-log`. For forensics, use `jq`, for example
  `jq -c 'select(.context.category=="authorization")' storage/logs/security-*.log` or
  `jq -r 'select(.message=="auth.login.failed") | .context.ip' security-*.log | sort | uniq -c | sort -rn`.

### Next step (not implemented): central and tamper-evident

In production, ship the security log to a central server (rsyslog over TLS, Vector/Fluent Bit to Loki or
Elastic) where the application user has no delete rights, and add alert rules:

* 5 or more `auth.lockout` events per hour,
* 10 or more `authz.denied` events per user in 5 minutes,
* any `crypto.*` event at error level.
