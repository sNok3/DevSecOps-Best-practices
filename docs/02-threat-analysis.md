# 2. Threat analysis

## 2.1 Assets

| Asset | Why it matters |
|---|---|
| A1 Note contents and files | The reason the vault exists; confidential personal data (GDPR). |
| A2 User credentials (passwords, sessions) | Give access to A1. Passwords are often reused on other sites. |
| A3 Admin account | Can disable every user (availability) and read the security log. |
| A4 Keys (`APP_KEY`, `VAULT_FILE_KEY`) and other secrets | Whoever has them can decrypt A1 or forge sessions. |
| A5 Source code, pipeline and dependencies | A change here compromises every user at once (supply chain). |
| A6 Security log | The evidence after an incident; an attacker wants to erase or flood it. |

## 2.2 Attackers, motivation and goals

| ID | Attacker | Motivation | Goal(s) | Capability |
|---|---|---|---|---|
| T1 | Opportunistic outsider / bot | Money (resell accounts, data), fun | Take over accounts, steal data in bulk | Automated tools, leaked password lists |
| T2 | Curious or malicious **other user** | Curiosity, stalking, competition | Read or change someone else's notes/files | A normal account; tampers with requests (Burp/DevTools) |
| T3 | Targeted attacker | Data of one specific person (ex-partner, journalist) | Get into one account | Phishing, social engineering, time |
| T4 | Insider: admin | Curiosity, revenge | Read user data, hide own actions | Admin role |
| T5 | Thief of server/backup | Money | Read the database or file storage offline | Disk image, DB dump, backup |
| T6 | Supply-chain attacker | Reach many victims at once | Run code in the app or the pipeline | Compromised package, GitHub Action, or developer account |

## 2.3 Attack trees

Notation: `OR` = any child is enough, `AND` = all children are needed. Leaves are concrete attacks; the
code in brackets refers to the misuse case (chapter 3) and the test that covers it.

### Goal G1 – Read another user's notes or files

```
G1 Read another user's notes/files  [OR]
├── 1 Log in as the victim  [OR]
│   ├── 1.1 Brute force / guess the password  ............................ MC-01
│   ├── 1.2 Credential stuffing with a leaked password list  ............. MC-01
│   ├── 1.3 Phishing the password  ....................................... (out of scope: user awareness, MFA = future)
│   └── 1.4 Steal the session  [OR]
│       ├── 1.4.1 XSS that reads the cookie / acts as the user  .......... MC-05
│       ├── 1.4.2 Sniff the cookie on plain HTTP  ........................ MC-11
│       └── 1.4.3 Session fixation  ...................................... MC-01
├── 2 Abuse the application as another logged-in user  [OR]
│   ├── 2.1 Change the id in /notes/{id} or /files/{id} (IDOR)  .......... MC-04, MC-08
│   ├── 2.2 Mass assignment: post user_id of the victim  ................. MC-04
│   ├── 2.3 SQL injection to dump the notes table  ....................... MC-06
│   └── 2.4 CSRF: make the victim's browser post a request  .............. MC-13
├── 3 Read the storage directly  [AND]
│   ├── 3.1 Get a DB dump, disk image or backup  ......................... MC-06, MC-09
│   └── 3.2 Have the encryption keys  .................................... MC-14
└── 4 Abuse the admin role  [AND]
    ├── 4.1 Become admin (privilege escalation at sign-up, or admin takeover) .. MC-03, MC-10
    └── 4.2 Admin can read content  ...................................... MC-10 (blocked: admins cannot)
```

### Goal G2 – Run code on the server or in the pipeline

```
G2 Run attacker code  [OR]
├── 1 Upload a web shell  [AND]
│   ├── 1.1 Upload a .php file or PHP disguised as an image  ............. MC-07
│   └── 1.2 Reach it through a URL  ...................................... MC-07 (files are never in public/)
├── 2 Path traversal in the file name to write outside storage  .......... MC-07
├── 3 Vulnerable dependency (Composer / npm)  ............................. MC-15
├── 4 Malicious or hijacked GitHub Action  ................................ MC-16
└── 5 Push malicious code directly to main  ............................... MC-16
```

### Goal G3 – Make the vault unavailable or hide an attack

```
G3 Disrupt / cover tracks  [OR]
├── 1 Lock users out by flooding failed logins  .......................... MC-01 (lock is per e-mail+IP)
├── 2 Fill the disk with uploads  ......................................... MC-07 (size + rate limit)
├── 3 Admin disables all accounts or themselves  .......................... MC-10
├── 4 Inject fake lines into the log (CR/LF in input)  .................... MC-12 (JSON encoding)
└── 5 Learn internals from error pages (stack trace, DB password)  ........ MC-12
```

### Goal G4 – Find out who has an account (enumeration)

```
G4 Enumerate accounts  [OR]
├── 1 Different login error for unknown e-mail  ........................... MC-02
├── 2 Faster response for unknown e-mail (no hash computed)  .............. MC-02
└── 3 403 vs 404 on someone else's note id  ............................... MC-04 (always 404)
```

## 2.4 Risk analysis

`Risk = Probability × Impact`, both on a 1–3 scale (1 = low, 2 = medium, 3 = high), **before** the
countermeasures (inherent risk). Score 6–9 = high, 3–4 = medium, 1–2 = low.

| ID | Threat (attack) | Attacker | P | I | Risk |
|---|---|---|---|---|---|
| R1 | Credential stuffing / brute force on login | T1 | 3 | 3 | **9 high** |
| R2 | IDOR: read other users' notes/files by changing the id | T2 | 3 | 3 | **9 high** |
| R3 | Stored XSS in note title/body | T2, T3 | 2 | 3 | **6 high** |
| R4 | Secret (APP_KEY, DB password) leaked via repository or logs | T1, T6 | 2 | 3 | **6 high** |
| R5 | Vulnerable / malicious dependency or GitHub Action | T6 | 2 | 3 | **6 high** |
| R6 | Malicious file upload (web shell) | T1, T2 | 2 | 3 | **6 high** |
| R7 | Stolen DB dump / backup read offline | T5 | 1 | 3 | 3 medium |
| R8 | SQL injection | T1, T2 | 1 | 3 | 3 medium |
| R9 | Privilege escalation (become admin via mass assignment) | T2 | 1 | 3 | 3 medium |
| R10 | CSRF on note/admin actions | T3 | 1 | 2 | 2 low |
| R11 | Information leak through error pages | T1 | 2 | 2 | 4 medium |
| R12 | Admin abuses role (reads data, hides actions) | T4 | 1 | 3 | 3 medium |
| R13 | User enumeration | T1 | 3 | 1 | 3 medium |
| R14 | Clickjacking / session theft over HTTP | T3 | 1 | 2 | 2 low |
| R15 | Denial of service through lockout or upload flood | T1 | 2 | 1 | 2 low |

### Risk matrix (3×3)

| Probability ↓ / Impact → | 1 low | 2 medium | 3 high |
|---|---|---|---|
| **3 high** | R13 | | **R1, R2** |
| **2 medium** | R15 | R11 | **R3, R4, R5, R6** |
| **1 low** | | R10, R14 | R7, R8, R9, R12 |

## 2.5 Conclusion: biggest risks

1. **R1 account takeover by credential stuffing / brute force**: the most likely attack on any login page,
   automated, and it gives full access to a victim's data.
2. **R2 broken access control (IDOR)**: number 1 in the OWASP Top 10 (A01:2021); trivial to try with a
   browser and invisible without logging.
3. **R3–R6** (XSS, leaked secrets, supply chain, malicious upload): each needs a bit more effort but has
   the same high impact.

These are the risks that get a detailed technical design and implementation (chapter 4); the medium and
low risks are covered by framework defaults that are verified by tests.
