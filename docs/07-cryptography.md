# 7. Cryptography

## 7.1 Secure password storage (OWASP Password Storage Cheat Sheet)

**Never store a password; store a slow, salted, one-way hash of it.** A normal hash such as SHA-256 is
designed to be *fast*: a GPU computes billions per second, so a stolen database of SHA-256 hashes is
cracked in hours. A password hashing function is made *slow and memory-hungry on purpose*.

What the vault does:

```php
// config/hashing.php — the default driver is argon2id
'driver' => env('HASH_DRIVER', 'argon2id'),
'argon' => ['memory' => 65536, 'threads' => 1, 'time' => 4],   // 64 MiB, 4 passes, 1 lane

// app/Models/User.php — the `hashed` cast calls Hash::make() whenever a password is assigned
'password' => 'hashed',
```

A stored value looks like this:

```
$argon2id$v=19$m=65536,t=4,p=1$c2FsdHNhbHRzYWx0$5u4gUhP8x9...   (shortened)
 algorithm  ver  parameters       salt (16 random bytes)  hash (32 bytes)
```

How it works, step by step:

1. **Salt:** at registration PHP's `password_hash()` (which Laravel calls) generates 16 random bytes with
   a CSPRNG. Two users with the same password get different hashes, and pre-computed rainbow tables are useless.
2. **Argon2id** fills 64 MiB of memory with blocks derived from password + salt and passes over it 4
   times, in a data-dependent order (the "d" part) after a first data-independent pass (the "i" part,
   against side-channel attacks). An attacker has to spend the same 64 MiB *for every guess*, and that
   makes GPU/ASIC cracking expensive. OWASP's minimum is `m=19 MiB, t=2, p=1`; the vault is above it.
3. **Everything needed to verify** (algorithm, parameters, salt) is stored inside the string, so the
   parameters can be raised later without breaking old hashes.
4. **Login:** `Hash::check($input, $stored)` calls `password_verify()`. It reads the parameters and salt
   from the stored string, recomputes the hash, and compares the two in constant time.
5. **Upgrades:** after a successful login `Hash::needsRehash()` checks whether the stored parameters are
   older than the configuration. If so, the password (which is known at that moment) is hashed again
   (`crypto.password.rehashed`).
6. **Equal timing:** for an unknown e-mail the login still verifies against a dummy hash, so the response
   time does not reveal whether the account exists.
7. **Before hashing:** passwords of 12–128 characters, spaces and passphrases allowed, and no
   composition rules (NIST SP 800-63B). Passwords found in data breaches are rejected through the Pwned
   Passwords API using *k-anonymity*: only the first 5 hex characters of the SHA-1 are sent, and the API
   returns every suffix with that prefix, so the password never leaves the server. If the API is
   unreachable, Laravel lets the password through (it fails open for availability). This is a deliberate
   trade-off.

A self-made demo you can run (`php artisan tinker`), to explain it in your own words:

```php
$h1 = Hash::make('correct horse battery staple');
$h2 = Hash::make('correct horse battery staple');
$h1 === $h2;                                              // false: different salt
Hash::check('correct horse battery staple', $h1);         // true
Hash::check('Correct horse battery staple', $h1);         // false
password_get_info($h1);                                   // ['algoName' => 'argon2id', 'options' => [...]]
$t = microtime(true); Hash::make('x'); microtime(true) - $t; // ~0.05–0.1 s on purpose
```

Tests: `AuthenticationTest::test_password_is_stored_as_argon2id_hash`, `test_breached_password_is_rejected`.

## 7.2 Where encryption is needed (and implemented)

| Data | Threat | Protection | Algorithm |
|---|---|---|---|
| Note title and body | DB dump, backup theft, SQL injection read (R7, R8) | Laravel `encrypted` cast, per column | AES-256-GCM, key `APP_KEY` |
| Original file names | Same | `encrypted` cast | AES-256-GCM, key `APP_KEY` |
| File contents | Disk or backup theft, tampering, swapping files (R7, MC-09) | `App\Support\FileCrypto` | AES-256-GCM, key `VAULT_FILE_KEY`, record id as AAD |
| Session data and cookies | Cookie theft, tampering | `SESSION_ENCRYPT=true`, encrypted cookies | AES-256-GCM, key `APP_KEY` |
| Passwords | DB dump | Hashing, not encryption (7.1) | Argon2id |
| Data in transit | Sniffing (MC-11) | TLS 1.3 + HSTS, Secure cookies | see 7.4 |

*Not encrypted (on purpose):* ids, timestamps, sizes, MIME types and the SHA-256 of a file. They are
needed for queries and integrity checks, and they reveal little. Full-disk encryption (LUKS) or the
database's own encryption at rest is still recommended in production as an extra layer against a stolen
disk, but it does not help against SQL injection, because the database decrypts transparently for any
query. That's why the vault also encrypts per column in the application.

### Strength of the crypto

| Property | Choice | Why |
|---|---|---|
| Algorithm | **AES** (Rijndael, 128-bit block) | NIST standard (FIPS 197), hardware support (AES-NI), no practical attacks |
| Key length | **256 bits** (32 random bytes, `random_bytes` = OS CSPRNG) | 2^256 brute force is infeasible; 256-bit keys also keep a 128-bit security margin against Grover's quantum search |
| Mode | **GCM** (Galois/Counter Mode) | An *AEAD* mode: confidentiality (counter mode) **and** integrity/authenticity (128-bit GHASH tag) in one step. Any change to the ciphertext, IV, tag or AAD makes decryption fail |
| IV / nonce | **96 bits, random per encryption** | The size GCM is designed for. A random 96-bit nonce is safe for about 2^32 encryptions per key (NIST SP 800-38D); the vault is far below that |
| AAD | File: the record's `stored_name` (UUID) | Binds the ciphertext to its record: copying Alice's encrypted file over Mallory's record fails |
| Format | `VF1` version prefix + IV + tag + ciphertext | The version prefix allows a future algorithm change |

Laravel's default cipher is AES-256-**CBC** with a separate HMAC-SHA256 (encrypt-then-MAC). That is also
secure, but the vault switches to GCM (`config/app.php` → `'cipher' => 'AES-256-GCM'`) for built-in
authentication. **Never** use ECB (identical blocks give identical ciphertext) or CBC without a MAC
(padding-oracle attacks).

**Key management:** two independent keys (`APP_KEY`, `VAULT_FILE_KEY`) that must differ (checked at
boot). Rotating `APP_KEY` is possible with `APP_PREVIOUS_KEYS`. Future work: envelope encryption with a
KMS (chapter 4).

Tests: `tests/Unit/FileCryptoTest.php` (round trip, random IV, flipped bit, moved ciphertext, wrong key,
key length), and `NoteTest::test_note_is_encrypted_in_the_database`, which reads the raw row and checks
that the GCM `tag` is present.

## 7.3 Certificate revocation: OCSP and CRL

A certificate can be **revoked** before it expires, for example when its private key leaks. A client
learns about this in one of these ways:

| Mechanism | How it works | Downsides |
|---|---|---|
| **CRL** (Certificate Revocation List, RFC 5280) | The CA publishes a signed list of revoked serial numbers at the *CRL Distribution Point* URL in the certificate; the client downloads it | Lists can be large; they are cached and updated only periodically |
| **OCSP** (Online Certificate Status Protocol, RFC 6960) | The client asks the CA's *OCSP responder* (URL in the *Authority Information Access* extension) "is serial X still good?" and gets a signed good/revoked/unknown | Privacy (the CA sees which sites you visit), latency, and clients "soft-fail" when the responder is down |
| **OCSP stapling** | The *server* fetches the OCSP response and sends it in the TLS handshake | Solves privacy and latency; only works if the server is configured for it |
| **Browser push lists** (CRLite in Firefox, CRLSets in Chrome) | The browser vendor pushes compressed revocation data | Browsers only |

Trend: the CA/Browser Forum made OCSP optional and CRLs mandatory for public TLS certificates, and
Let's Encrypt stopped its OCSP service in 2025. New certificates therefore often contain **only** a CRL URL.

### Checked in the vault

The vault makes one outbound HTTPS connection: PHP's curl to `api.pwnedpasswords.com` (breach check).

```
$ echo | openssl s_client -connect api.pwnedpasswords.com:443 -servername api.pwnedpasswords.com -status
OCSP response: no response sent              <- the server does not staple OCSP
Verification: OK                             <- chain and hostname valid

$ openssl x509 -in cert.pem -noout -issuer -ocsp_uri -ext crlDistributionPoints
issuer=C = US, O = Google Trust Services, CN = WE1
                                             <- no OCSP URI in the certificate
X509v3 CRL Distribution Points:
    URI:http://c.pki.goog/we1/PNtNmT7SD6A.crl  <- revocation only via CRL

$ php -r '... curl_setopt($c, CURLOPT_SSL_VERIFYSTATUS, true) ...'
VERIFYSTATUS (require OCSP staple): FAILED No OCSP response received
```

**Conclusion:** PHP/curl (OpenSSL backend) checks the chain, expiry and hostname, but **does not check
revocation by default**. It doesn't download the CRL and has no stapled OCSP response to look at. Forcing
stapling (`CURLOPT_SSL_VERIFYSTATUS`) would break the connection, because this server does not staple.
Possible improvements: download and check the CRL periodically (`CURLOPT_CRLFILE`), or accept the risk
for this low-sensitivity call. Only a 5-character hash prefix is sent, so an attacker with a revoked
certificate learns very little. For the vault's *own* HTTPS endpoint, enable OCSP stapling in the web
server where the CA supports OCSP (`ssl_stapling on; ssl_stapling_verify on;` in Nginx) and check it with
`openssl s_client -status`.

To capture it on the wire: `tcpdump -i any -w tls.pcap host api.pwnedpasswords.com`, then open the file
in Wireshark with the filter `tls.handshake.type == 1 || tls.handshake.type == 22` (ClientHello /
CertificateStatus). There is no CertificateStatus message, and no OCSP/CRL HTTP request on port 80.

## 7.4 Is the TLS post-quantum ready?

A future quantum computer running Shor's algorithm breaks RSA and elliptic-curve cryptography (ECDHE,
ECDSA). The urgent part is **key exchange**: an attacker can record TLS traffic today and decrypt it
later ("harvest now, decrypt later"). Symmetric AES-256 and SHA-384 are not seriously weakened. The fix is
**hybrid key exchange `X25519MLKEM768`**: classical X25519 plus ML-KEM-768 (FIPS 203, formerly Kyber),
so the connection stays secure if either part holds. It is supported by OpenSSL 3.5+, Chrome, Firefox and
Cloudflare.

Measured from the development server:

```
# System OpenSSL 3.0 (too old for ML-KEM)
$ openssl s_client -connect api.pwnedpasswords.com:443
Protocol: TLSv1.3   Cipher: TLS_AES_256_GCM_SHA384   Server Temp Key: X25519, 253 bits   Peer signature: ECDSA
$ openssl s_client -connect api.pwnedpasswords.com:443 -groups X25519MLKEM768
error: group 'X25519MLKEM768' cannot be set

# The PHP runtime used by the vault: curl 8.20 with OpenSSL 3.6.2
X25519MLKEM768: handshake OK
X25519:         handshake OK
```

| Part | Status | PQ-ready? |
|---|---|---|
| Protocol | TLS 1.3 | ✔ required for the hybrid groups |
| Cipher suite | `TLS_AES_256_GCM_SHA384` | ✔ (AES-256 keeps 128-bit security against Grover) |
| Key exchange | `X25519MLKEM768` is accepted by the server and offered by PHP's OpenSSL 3.6 | ✔ **yes** with the vault's PHP; ✘ with OpenSSL 3.0 tools (classical X25519 only) |
| Certificate signature | ECDSA | ✘ not yet. PQ signatures (ML-DSA, FIPS 204) are not yet in public WebPKI certificates. This is less urgent, because a signature cannot be broken retroactively |

**SSH:** OpenSSH 9.0+ uses `sntrup761x25519-sha512` by default, and OpenSSH 9.9+ adds `mlkem768x25519-sha256`
(the default since 10.0). Check with `ssh -Q kex` (supported) and `ssh -vv host 2>&1 | grep "kex: algorithm"` (negotiated).

**For the vault's own deployment:** serve it with a TLS stack on OpenSSL 3.5+ (or BoringSSL/Cloudflare),
allow only TLS 1.2+ (preferably 1.3 only), and verify with `openssl s_client -groups X25519MLKEM768`
(OpenSSL 3.5+) or Qualys SSL Labs.
